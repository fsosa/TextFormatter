<?php

namespace s9e\TextFormatter\Tests;

use ReflectionClass,
    s9e\TextFormatter\Tests\Test,
    s9e\TextFormatter\JSParserGenerator;

include_once __DIR__ . '/Test.php';
include_once __DIR__ . '/../src/JSParserGenerator.php';

/**
* @covers s9e\TextFormatter\JSParserGenerator
*/
class JSParserGeneratorTest extends Test
{
	protected function initJSPG()
	{
		$this->call($this->jspg, 'init', array(array()));
	}

	protected function encodeArray(array $arr)
	{
		return $this->call(
			$this->jspg,
			'encodeArray',
			func_get_args()
		);
	}

	protected function encodePluginConfig(array $config)
	{
		return $this->call(
			$this->jspg,
			'encodePluginConfig',
			func_get_args()
		);
	}

	/**
	* @testdox encodeArray() can encode arrays to objects
	*/
	public function test_encodeArray_can_encode_arrays_to_objects()
	{
		$arr = array(
			'a' => 1,
			'b' => 2
		);

		$this->assertSame(
			'{a:1,b:2}',
			$this->encodeArray($arr)
		);
	}

	/**
	* @testdox encodeArray() can encode arrays to Arrays
	*/
	public function test_encodeArray_can_encode_arrays_to_Arrays()
	{
		$arr = array(1, 2);

		$this->assertSame(
			'[1,2]',
			$this->encodeArray($arr)
		);
	}

	/**
	* @testdox encodeArray() can convert regexp strings to RegExp objects
	*/
	public function test_encodeArray_can_convert_regexp_strings_to_RegExp_objects()
	{
		$arr = array('/foo/');

		$meta = array(
			'isRegexp' => array(
				array(true)
			)
		);

		$this->assertContains(
			'/foo/',
			$this->encodeArray($arr, $meta)
		);
	}

	/**
	* @testdox encodeArray() can convert regexp strings to RegExp objects with g flag
	*/
	public function test_encodeArray_can_convert_regexp_strings_to_RegExp_objects_with_g_flag()
	{
		$arr = array('/foo/');

		$meta = array(
			'isGlobalRegexp' => array(
				array(true)
			)
		);

		$this->assertContains(
			'/foo/g',
			$this->encodeArray($arr, $meta)
		);
	}

	/**
	* @testdox encode() encodes booleans to 0 and 1
	* @depends test_encodeArray_can_encode_arrays_to_Arrays
	*/
	public function test_encode_encodes_booleans_to_0_and_1()
	{
		$this->assertSame(
			'[1,0,1]',
			$this->call(
				$this->jspg,
				'encode',
				array(array(true, false, true))
			)
		);
	}

	/**
	* @testdox encodeArray() can preserve a key of an array
	* @depends test_encodeArray_can_encode_arrays_to_objects
	*/
	public function test_encodeArray_can_preserve_a_key_of_an_array()
	{
		$arr = array(
			'a' => 1,
			'b' => 2
		);

		$meta = array(
			'preserveKeys' => array(
				array('a')
			)
		);

		$this->assertSame(
			'{"a":1,b:2}',
			$this->encodeArray($arr, $meta)
		);
	}

	/**
	* @testdox encodeArray() can preserve a key of a nested array
	* @depends test_encodeArray_can_preserve_a_key_of_an_array
	*/
	public function test_encodeArray_can_preserve_a_key_of_a_nested_array()
	{
		$arr = array(
			'a' => array('z' => 1, 'b' => 2),
			'b' => 2
		);

		$meta = array(
			'preserveKeys' => array(
				array('a', 'z')
			)
		);

		$this->assertSame(
			'{a:{"z":1,b:2},b:2}',
			$this->encodeArray($arr, $meta)
		);
	}

	/**
	* @testdox encodeArray() preserves keys at the correct depth
	* @depends test_encodeArray_can_preserve_a_key_of_a_nested_array
	*/
	public function test_encodeArray_preserves_keys_at_the_correct_depth()
	{
		$arr = array(
			'a' => array('a' => 1, 'b' => 2),
			'b' => 2
		);

		$meta = array(
			'preserveKeys' => array(
				array('a', 'a')
			)
		);

		$this->assertSame(
			'{a:{"a":1,b:2},b:2}',
			$this->encodeArray($arr, $meta)
		);
	}

	/**
	* @testdox encodeArray() can use TRUE as a wildcard
	* @depends test_encodeArray_can_preserve_a_key_of_an_array
	*/
	public function test_encodeArray_can_use_TRUE_as_a_wildcard()
	{
		$arr = array(
			'a' => array('a' => 1, 'b' => 2),
			'b' => array('a' => 1, 'b' => 2)
		);

		$meta = array(
			'preserveKeys' => array(
				array('a', true)
			)
		);

		$this->assertSame(
			'{a:{"a":1,"b":2},b:{a:1,b:2}}',
			$this->encodeArray($arr, $meta)
		);
	}

	/**
	* @testdox encodeArray() preserves reserved words
	*/
	public function test_encodeArray_preserves_reserved_words()
	{
		$arr = array(
			'a'    => 1,
			'with' => 2
		);

		$this->assertSame(
			'{a:1,"with":2}',
			$this->encodeArray($arr)
		);
	}

	/**
	* @testdox encodeArray() can preserve raw JS
	*/
	public function test_encodeArray_can_preserve_raw_JS()
	{
		$arr = array(
			'a' => 1,
			'b' => 'foo()'
		);

		$meta = array(
			'isRawJS' => array(
				array('b')
			)
		);

		$this->assertSame(
			'{a:1,b:foo()}',
			$this->encodeArray($arr, $meta)
		);
	}

	/**
	* @testdox generateFiltersConfig() returns allowedSchemes regexp as an object
	*/
	public function test_generateFiltersConfig_returns_allowedSchemes_regexp_as_an_object()
	{
		$this->initJSPG();

		$this->assertContains(
			'allowedSchemes:/^https?$/i',
			$this->call($this->jspg, 'generateFiltersConfig')
		);
	}

	/**
	* @testdox generateFiltersConfig() returns disallowedHosts regexp as an object
	*/
	public function test_generateFiltersConfig_returns_disallowedHosts_regexp_as_an_object()
	{
		$this->cb->disallowHost('example.com');
		$this->initJSPG();

		$this->assertContains(
			'disallowedHosts:/',
			$this->call($this->jspg, 'generateFiltersConfig')
		);
	}

	/**
	* @testdox generateFiltersConfig() converts unsupported lookbehind assertions from disallowedHosts regexp
	* @depends test_generateFiltersConfig_returns_disallowedHosts_regexp_as_an_object
	*/
	public function test_generateFiltersConfig_converts_unsupported_lookbehind_assertions_from_disallowedHosts_regexp()
	{
		$this->cb->disallowHost('example.com');
		$this->initJSPG();

		$this->assertContains(
			'/(?:^|\\.)example\\.com$/i',
			$this->call($this->jspg, 'generateFiltersConfig')
		);
	}

	/**
	* @testdox encodePluginConfig() removes parserClassName from config
	* @depends test_encodeArray_can_encode_arrays_to_objects
	*/
	public function test_encodePluginConfig_removes_parserClassName_from_config()
	{
		$this->assertSame(
			'{foo:1}',
			$this->encodePluginConfig(
				array(
					'parserClassName' => 'foo',
					'foo' => 1
				),
				array()
			)
		);
	}

	/**
	* @testdox encodePluginConfig() removes parserFilepath from config
	* @depends test_encodeArray_can_encode_arrays_to_objects
	*/
	public function test_encodePluginConfig_removes_parserFilepath_from_config()
	{
		$this->assertSame(
			'{foo:1}',
			$this->encodePluginConfig(
				array(
					'parserFilepath' => 'foo',
					'foo' => 1
				),
				array()
			)
		);
	}

	/**
	* @testdox encodePluginConfig() convert scalar regexp to a RegExp object with g flag
	* @depends test_encodeArray_can_encode_arrays_to_objects
	*/
	public function test_encodePluginConfig_convert_scalar_regexp_to_a_RegExp_object_with_g_flag()
	{
		$this->assertSame(
			'{regexp:/foo/g}',
			$this->encodePluginConfig(
				array(
					'regexp' => '#foo#'
				),
				array()
			)
		);
	}

	/**
	* @testdox encodePluginConfig() convert array regexp to an object with RegExp objects with g flag as properties
	* @depends test_encodeArray_can_encode_arrays_to_objects
	*/
	public function test_encodePluginConfig_convert_array_regexp_to_an_object_with_RegExp_objects_with_g_flag_as_properties()
	{
		$this->assertSame(
			'{regexp:{bar:/bar/g,baz:/baz/g}}',
			$this->encodePluginConfig(
				array(
					'regexp' => array(
						'bar' => '#bar#',
						'baz' => '#baz#'
					)
				),
				array()
			)
		);
	}

	/**
	* @test
	*/
	public function Injects_plugins_parsers_into_source()
	{
		$this->cb->loadPlugin('Autolink');

		$jsParser = $this->jspg->get();

		$this->assertContains(
			'parser:function(',
			$jsParser
		);
	}

	/**
	* @test
	*/
	public function Injects_plugins_configs_into_source()
	{
		$this->cb->loadPlugin('Autolink');

		$jsParser = $this->jspg->get();

		$this->assertContains(
			'pluginsConfig={"Autolink":{',
			$jsParser
		);
	}

	/**
	* @testdox A regexp map is created for compound attributes
	*/
	public function testCompoundAttributesHaveARegexpMap()
	{
		$this->cb->addTag('X');
		$this->cb->addTagAttribute('X', 'x', 'compound', array(
			'regexp' => '#^(?<width>[0-9]+),(?<height>[0-9]+)$#'
		));

		$this->initJSPG();

		$this->assertContains(
			'attrs:{"x":{isRequired:0,regexp:/^([0-9]+),([0-9]+)$/,type:"compound",regexpMap:{width:1,height:2}}}',
			$this->call($this->jspg, 'generateTagsConfig')
		);
	}

	/**
	* @testdox The source is sent to Google Closure Compiler if "compilation" is not set to "none"
	*/
	public function test_Closure_Compiler()
	{
		$this->assertSame(
			'alert("Hello world");',
			$this->jspg->get(array(
				'compilationLevel' => 'ADVANCED_OPTIMIZATIONS',
				'closureCompilerURL' => 'data:text/plain,{"compiledCode":"alert(\"Hello world\");","statistics":{"originalSize":86,"originalGzipSize":96,"compressedSize":21,"compressedGzipSize":41,"compileTime":1}}'
			))
		);
	}

	/**
	* @testdox JSParserGenerator throws an exception if an error occurs while contacting to Google Closure Compiler
	* @expectedException RuntimeException
	* @expectedExceptionMessage An error occured while contacting Google Closure Compiler
	*/
	public function testThrowsAnExceptionIfAnErrorOccursWhileContactingGoogleClosureCompiler()
	{
		$this->jspg->get(array(
			'compilationLevel' => 'ADVANCED_OPTIMIZATIONS',
			'closureCompilerURL' => 'data:text/plain,FAILURE'
		));
	}

	/**
	* @testdox JSParserGenerator throws an exception if Google Closure Compiler returns an error
	* @expectedException RuntimeException
	* @expectedExceptionMessage An error has been returned Google Closure Compiler: 'Parse error. missing ; before statement'
	*/
	public function testThrowsAnExceptionIfGoogleClosureCompilerReturnsAnError()
	{
		$this->jspg->get(array(
			'compilationLevel' => 'ADVANCED_OPTIMIZATIONS',
			'closureCompilerURL' => 'data:text/plain,{"compiledCode":"","errors":[{"type":"JSC_PARSE_ERROR","file":"Input_0","lineno":1,"charno":7,"error":"Parse error. missing ; before statement","line":"foo bar"}],"statistics":{"originalSize":7,"originalGzipSize":27,"compressedSize":0,"compressedGzipSize":20,"compileTime":0}}'
		));
	}

	/**
	* @testdox The "unsafeMinification" option indiscriminately renames all occurences of properties that share their name with DOM properties, except if their variable's names ends with "Node" or "Attr"
	*/
	public function testUnsafeMinification()
	{
		$js = $this->cb->getJSParser(array(
			'unsafeMinification' => true
		));

		$names= implode('|', array(
			'id',
			'name',
			'type',
			'rules',
			'defaultValue',
			'tagName',
			'attrName'
		));

		$this->assertNotRegexp(
			'#(?<!Node|Attr)\\.(?:' . $names . ')#',
			$js,
			'A reserved name is used by a property of a variable whose name does not end with "Node" or "Attr"'
		);
	}

	/**
	* @dataProvider getHintsData
	*/
	public function testHints($desc, array $tagOptions = array(), array $compilerOptions = array(), $callback = null)
	{
		if (!preg_match('#^([A-Za-z.0-9]+) is (false|true)#', $desc, $m))
		{
			$this->fail("Wrong test description '" . $desc . "'");
		}

		$hintName      = $m[1];
		$expectedValue = (int) ($m[2] === 'true');

		$this->cb->addTag('X', $tagOptions);

		if (isset($callback))
		{
			$callback($this->cb);
		}

		$this->assertContains(
			$hintName . '=' . $expectedValue,
			$this->jspg->get($compilerOptions + array(
				'setOptimizationHints' => true
			)),
			'Cannot assert that ' . $desc
		);
	}

	public function getHintsData()
	{
		return array(
/**
			array(
				'HINT.attrConfig.defaultValue is false by default'
			),
			array(
				'HINT.attrConfig.defaultValue is true is any attribute has a default value set',
				array(
					'attrs' => array(
						'foo' => array('type' => 'int', 'defaultValue' => 1)
					)
				)
			),
			array(
				'HINT.attrConfig.defaultValue is true even if the default value is 0',
				array(
					'attrs' => array(
						'foo' => array('type' => 'int', 'defaultValue' => 0)
					)
				)
			),
			array(
				'HINT.attrConfig.isRequired is false by default'
			),
			array(
				'HINT.attrConfig.isRequired is true is any attribute has the "isRequired" option enabled',
				array(
					'attrs' => array(
						'foo' => array('type' => 'int', 'isRequired' => true)
					)
				)
			),
			array(
				'HINT.attrConfig.preFilter is false by default'
			),
			array(
				'HINT.attrConfig.preFilter is true if any attribute has a preFilter callback set',
				array(
					'attrs' => array(
						'foo' => array(
							'type' => 'int',
							'preFilter' => array('strtolower')
						)
					)
				)
			),
			array(
				'HINT.attrConfig.postFilter is false by default'
			),
			array(
				'HINT.attrConfig.postFilter is true if any attribute has a postFilter callback set',
				array(
					'attrs' => array(
						'foo' => array(
							'type' => 'int',
							'postFilter' => array('strtolower')
						)
					)
				)
			),
			array(
				'HINT.disabledAPI.parse is false by default'
			),
			array(
				'HINT.disabledAPI.parse is true if the "disableAPI" config array contains "parse"',
				array(),
				array('disableAPI' => array('parse'))
			),
			array(
				'HINT.disabledAPI.render is false by default'
			),
			array(
				'HINT.disabledAPI.render is true if the "disableAPI" config array contains "render"',
				array(),
				array('disableAPI' => array('render'))
			),
			array(
				'HINT.disabledAPI.getLog is false by default'
			),
			array(
				'HINT.disabledAPI.getLog is true if the "disableAPI" config array contains "getLog"',
				array(),
				array('disableAPI' => array('getLog'))
			),
			array(
				'HINT.disabledAPI.enablePlugin is false by default'
			),
			array(
				'HINT.disabledAPI.enablePlugin is true if the "disableAPI" config array contains "enablePlugin"',
				array(),
				array('disableAPI' => array('enablePlugin'))
			),
			array(
				'HINT.disabledAPI.disablePlugin is false by default'
			),
			array(
				'HINT.disabledAPI.disablePlugin is true if the "disableAPI" config array contains "disablePlugin"',
				array(),
				array('disableAPI' => array('disablePlugin'))
			),
			array(
				'HINT.disabledAPI.preview is false by default'
			),
			array(
				'HINT.disabledAPI.preview is true if the "disableAPI" config array contains "preview"',
				array(),
				array('disableAPI' => array('preview'))
			),
			array(
				'HINT.disabledLogTypes.debug is false by default'
			),
			array(
				'HINT.disabledLogTypes.debug is true if the "disableLogTypes" config array contains "debug"',
				array(),
				array('disableLogTypes' => array('debug'))
			),
			array(
				'HINT.disabledLogTypes.error is false by default'
			),
			array(
				'HINT.disabledLogTypes.error is true if the "disableLogTypes" config array contains "error"',
				array(),
				array('disableLogTypes' => array('error'))
			),
			array(
				'HINT.disabledLogTypes.warning is false by default'
			),
			array(
				'HINT.disabledLogTypes.warning is true if the "disableLogTypes" config array contains "warning"',
				array(),
				array('disableLogTypes' => array('warning'))
			),
			array(
				'HINT.enableIE is true by default'
			),
			array(
				'HINT.enableIE is false if the option "enableIE" is false',
				array(),
				array('enableIE' => false)
			),
			array(
				'HINT.enableIE7 is true by default'
			),
			array(
				'HINT.enableIE7 is false if the option "enableIE7" is false',
				array(),
				array('enableIE7' => false)
			),
			array(
				'HINT.enableIE7 is false if the option "enableIE" is false',
				array(),
				array('enableIE' => false)
			),
			array(
				'HINT.enableIE9 is true by default'
			),
			array(
				'HINT.enableIE9 is false if the option "enableIE9" is false',
				array(),
				array('enableIE9' => false)
			),
			array(
				'HINT.enableIE9 is false if the option "enableIE" is false',
				array(),
				array('enableIE' => false)
			),
			array(
				'HINT.filterConfig.email.forceUrlencode is false by default'
			),
			array(
				'HINT.filterConfig.email.forceUrlencode is true if any attribute of type "email" has the "forceUrlencode" option enabled',
				array(
					'attrs' => array(
						'foo' => array(
							'type' => 'email',
							'forceUrlencode' => true
						)
					)
				)
			),
			array(
				'HINT.filterConfig.email.forceUrlencode is false by default'
			),
			array(
				'HINT.filterConfig.email.forceUrlencode is true if any attribute of type "email" has the "forceUrlencode" option enabled',
				array(
					'attrs' => array(
						'foo' => array(
							'type' => 'email',
							'forceUrlencode' => true
						)
					)
				)
			),
			array(
				'HINT.filterConfig.regexp.replaceWith is false by default'
			),
			array(
				'HINT.filterConfig.regexp.replaceWith is true if any attribute of type "regexp" has the "replaceWith" option set',
				array(
					'attrs' => array(
						'foo' => array(
							'type' => 'regexp',
							'replaceWith' => 'foo'
						)
					)
				)
			),
			array(
				'HINT.filterConfig.regexp.replaceWith is true if any attribute of type "regexp" has the "replaceWith" option set to "0"',
				array(
					'attrs' => array(
						'foo' => array(
							'type' => 'regexp',
							'replaceWith' => '0'
						)
					)
				)
			),
			array(
				'HINT.filterConfig.url.disallowedHosts is false by default'
			),
/**/
			array(
				'HINT.filterConfig.url.disallowedHosts is true if any hostmask has been disallowed',
				array(),
				array(),
				function($cb)
				{
					$cb->disallowHost('example.com');
				}
			),
		);
	}
}