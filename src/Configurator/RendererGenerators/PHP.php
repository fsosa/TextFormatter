<?php

/**
* @package   s9e\TextFormatter
* @copyright Copyright (c) 2010-2017 The s9e Authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\TextFormatter\Configurator\RendererGenerators;

use DOMElement;
use s9e\TextFormatter\Configurator\Helpers\TemplateHelper;
use s9e\TextFormatter\Configurator\Helpers\TemplateParser;
use s9e\TextFormatter\Configurator\RendererGenerator;
use s9e\TextFormatter\Configurator\RendererGenerators\PHP\ControlStructuresOptimizer;
use s9e\TextFormatter\Configurator\RendererGenerators\PHP\Optimizer;
use s9e\TextFormatter\Configurator\RendererGenerators\PHP\Quick;
use s9e\TextFormatter\Configurator\RendererGenerators\PHP\Serializer;
use s9e\TextFormatter\Configurator\Rendering;

class PHP implements RendererGenerator
{
	/**
	* XSL namespace
	*/
	const XMLNS_XSL = 'http://www.w3.org/1999/XSL/Transform';

	/**
	* @var string Directory where the renderer's source is automatically saved if set, and if filepath is not set
	*/
	public $cacheDir;

	/**
	* @var string Name of the class to be created. If null, a random name will be generated
	*/
	public $className;

	/**
	* @var ControlStructuresOptimizer Control structures optimizer
	*/
	public $controlStructuresOptimizer;

	/**
	* @var string Prefix used when generating a default class name
	*/
	public $defaultClassPrefix = 'Renderer_';

	/**
	* @var bool Whether to enable the Quick renderer
	*/
	public $enableQuickRenderer = true;

	/**
	* @var string If set, path to the file where the renderer will be saved
	*/
	public $filepath;

	/**
	* @var string Name of the last class generated
	*/
	public $lastClassName;

	/**
	* @var string Path to the last file saved
	*/
	public $lastFilepath;

	/**
	* @var Optimizer Optimizer
	*/
	public $optimizer;

	/**
	* @var Serializer Serializer
	*/
	public $serializer;

	/**
	* @var bool Whether to use the mbstring functions as a replacement for XPath expressions
	*/
	public $useMultibyteStringFunctions;

	/**
	* Constructor
	*
	* @param string $cacheDir If set, path to the directory where the renderer will be saved
	*/
	public function __construct($cacheDir = null)
	{
		$this->cacheDir = (isset($cacheDir)) ? $cacheDir : sys_get_temp_dir();
		if (extension_loaded('tokenizer'))
		{
			$this->controlStructuresOptimizer = new ControlStructuresOptimizer;
			$this->optimizer = new Optimizer;
		}
		$this->useMultibyteStringFunctions = extension_loaded('mbstring');
		$this->serializer = new Serializer;
	}

	/**
	* {@inheritdoc}
	*/
	public function getRenderer(Rendering $rendering)
	{
		// Generate the source file
		$php = $this->generate($rendering);

		// Save the file if applicable
		if (isset($this->filepath))
		{
			$filepath = $this->filepath;
		}
		else
		{
			$filepath = $this->cacheDir . '/' . str_replace('\\', '_', $this->lastClassName) . '.php';
		}

		file_put_contents($filepath, "<?php\n" . $php);
		$this->lastFilepath = realpath($filepath);

		// Execute the source to create the class if it doesn't exist
		if (!class_exists($this->lastClassName, false))
		{
			include $filepath;
		}

		return new $this->lastClassName;
	}

	/**
	* Generate the source for a PHP class that renders an intermediate representation according to
	* given rendering configuration
	*
	* @param  Rendering $rendering
	* @return string
	*/
	public function generate(Rendering $rendering)
	{
		// Copy some options to the serializer
		$this->serializer->useMultibyteStringFunctions = $this->useMultibyteStringFunctions;

		// Gather templates and optimize simple templates
		$templates = $rendering->getTemplates();

		// Group templates by content to deduplicate them
		$groupedTemplates = [];
		foreach ($templates as $tagName => $template)
		{
			$groupedTemplates[$template][] = $tagName;
		}

		// Assign a branch number to each unique template and record the value for each tag
		$tagBranch   = 0;
		$tagBranches = [];

		// Store the compiled template for each branch
		$compiledTemplates = [];

		// Other branch tables
		$branchTables = [];

		// Parse each template and serialize it to PHP
		foreach ($groupedTemplates as $template => $tagNames)
		{
			// Parse the template
			$ir = TemplateParser::parse($template);

			// Serialize the representation to PHP
			$templateSource = $this->serializer->serialize($ir->documentElement);
			if (isset($this->optimizer))
			{
				$templateSource = $this->optimizer->optimize($templateSource);
			}

			// Record the branch tables used in this template
			$branchTables += $this->serializer->branchTables;

			// Record the source for this branch number and assign this number to each tag
			$compiledTemplates[$tagBranch] = $templateSource;
			foreach ($tagNames as $tagName)
			{
				$tagBranches[$tagName] = $tagBranch;
			}
			++$tagBranch;
		}

		// Unset the vars we don't need anymore
		unset($groupedTemplates, $ir, $quickRender);

		// Store the compiled template if we plan to create a Quick renderer
		$quickSource = false;
		if ($this->enableQuickRenderer)
		{
			$quickRender = [];
			foreach ($tagBranches as $tagName => $tagBranch)
			{
				$quickRender[$tagName] = $compiledTemplates[$tagBranch];
			}

			$quickSource = Quick::getSource($quickRender);
			unset($quickRender);
		}

		// Coalesce all the compiled templates
		$templatesSource = Quick::generateConditionals('$tb', $compiledTemplates);
		unset($compiledTemplates);

		// Start the code right after the class name, we'll prepend the header when we're done
		$php = [];
		$php[] = ' extends \\s9e\\TextFormatter\\Renderers\\PHP';
		$php[] = '{';
		$php[] = '	protected $params=' . self::export($rendering->getAllParameters()) . ';';
		$php[] = '	protected static $tagBranches=' . self::export($tagBranches) . ';';

		foreach ($branchTables as $varName => $branchTable)
		{
			$php[] = '	protected static $' . $varName . '=' . self::export($branchTable) . ';';
		}

		$php[] = '	protected function renderNode(\\DOMNode $node)';
		$php[] = '	{';
		$php[] = '		if (isset(self::$tagBranches[$node->nodeName]))';
		$php[] = '		{';
		$php[] = '			$tb = self::$tagBranches[$node->nodeName];';
		$php[] = '			' . $templatesSource;
		$php[] = '		}';
		$php[] = '		else';
		$php[] = '		{';
		$php[] = '			$this->at($node);';
		$php[] = '		}';
		$php[] = '	}';

		// Append the Quick renderer if applicable
		if ($quickSource !== false)
		{
			$php[] = $quickSource;
		}

		// Close the class definition
		$php[] = '}';

		// Assemble the source
		$php = implode("\n", $php);

		// Finally, optimize the control structures
		if (isset($this->controlStructuresOptimizer))
		{
			$php = $this->controlStructuresOptimizer->optimize($php);
		}

		// Generate a name for that class if necessary, and save it
		$className = (isset($this->className))
		           ? $this->className
		           : $this->defaultClassPrefix . sha1($php);
		$this->lastClassName = $className;

		// Prepare the header
		$header = "\n"
		        . "/**\n"
		        . "* @package   s9e\TextFormatter\n"
		        . "* @copyright Copyright (c) 2010-2017 The s9e Authors\n"
		        . "* @license   http://www.opensource.org/licenses/mit-license.php The MIT License\n"
		        . "*/\n";

		// Declare the namespace and class name
		$pos = strrpos($className, '\\');
		if ($pos !== false)
		{
			$header .= 'namespace ' . substr($className, 0, $pos) . ";\n\n";
			$className = substr($className, 1 + $pos);
		}

		// Prepend the header and the class name
		$php = $header . 'class ' . $className . $php;

		return $php;
	}

	/**
	* Export given array as PHP code
	*
	* @param  array  $value Original value
	* @return string        PHP code
	*/
	protected static function export(array $value)
	{
		$pairs = [];
		foreach ($value as $k => $v)
		{
			$pairs[] = var_export($k, true) . '=>' . var_export($v, true);
		}

		return '[' . implode(',', $pairs) . ']';
	}
}