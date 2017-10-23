<?php

namespace s9e\TextFormatter\Tests\Plugins\Litedown;

use s9e\TextFormatter\Configurator;
use s9e\TextFormatter\Plugins\Litedown\Parser;
use s9e\TextFormatter\Tests\Plugins\ParsingTestsRunner;
use s9e\TextFormatter\Tests\Plugins\ParsingTestsJavaScriptRunner;
use s9e\TextFormatter\Tests\Plugins\RenderingTestsRunner;
use s9e\TextFormatter\Tests\Test;

/**
* @covers s9e\TextFormatter\Plugins\Litedown\Parser
* @covers s9e\TextFormatter\Plugins\Litedown\Parser\Passes\Blocks
* @covers s9e\TextFormatter\Plugins\Litedown\Parser\Passes\Emphasis
* @covers s9e\TextFormatter\Plugins\Litedown\Parser\Passes\ForcedLineBreaks
* @covers s9e\TextFormatter\Plugins\Litedown\Parser\Passes\Images
* @covers s9e\TextFormatter\Plugins\Litedown\Parser\Passes\InlineCode
* @covers s9e\TextFormatter\Plugins\Litedown\Parser\Passes\LinkReferences
* @covers s9e\TextFormatter\Plugins\Litedown\Parser\Passes\Links
* @covers s9e\TextFormatter\Plugins\Litedown\Parser\Passes\Strikethrough
* @covers s9e\TextFormatter\Plugins\Litedown\Parser\Passes\Superscript
*/
class ParserTest extends Test
{
	use ParsingTestsRunner;
	use ParsingTestsJavaScriptRunner;
	use RenderingTestsRunner;

	public function getParsingTests()
	{
		return self::fixTests([
			[
				// Ensure that automatic line breaks can be enabled
				"First\nSecond",
				"<t><p>First<br/>\nSecond</p></t>",
				[],
				function ($configurator)
				{
					$configurator->rootRules->enableAutoLineBreaks();
				}
			],
			// Inline links
			[
				'Go to [that site](http://example.org) now!',
				'<r><p>Go to <URL url="http://example.org"><s>[</s>that site<e>](http://example.org)</e></URL> now!</p></r>'
			],
			[
				'Go to [that site] (http://example.org) now!',
				'<t><p>Go to [that site] (http://example.org) now!</p></t>'
			],
			[
				'En route to [Mars](http://en.wikipedia.org/wiki/Mars_(disambiguation))!',
				'<r><p>En route to <URL url="http://en.wikipedia.org/wiki/Mars_%28disambiguation%29"><s>[</s>Mars<e>](http://en.wikipedia.org/wiki/Mars_(disambiguation))</e></URL>!</p></r>'
			],
			[
				'Go to [\\[x\\[x\\]x\\]](http://example.org/?foo[]=1&bar\\[\\]=1) now!',
				'<r><p>Go to <URL url="http://example.org/?foo%5B%5D=1&amp;bar%5B%5D=1"><s>[</s>\\[x\\[x\\]x\\]<e>](http://example.org/?foo[]=1&amp;bar\\[\\]=1)</e></URL> now!</p></r>'
			],
			[
				'Check out my [~~lame~~ cool site](http://example.org) now!',
				'<r><p>Check out my <URL url="http://example.org"><s>[</s><DEL><s>~~</s>lame<e>~~</e></DEL> cool site<e>](http://example.org)</e></URL> now!</p></r>'
			],
			[
				'This is [an example](http://example.com/ "Link title") inline link.',
				'<r><p>This is <URL title="Link title" url="http://example.com/"><s>[</s>an example<e>](http://example.com/ "Link title")</e></URL> inline link.</p></r>'
			],
			[
				'This is [an example](http://example.com/ \'Link title\') inline link.',
				'<r><p>This is <URL title="Link title" url="http://example.com/"><s>[</s>an example<e>](http://example.com/ \'Link title\')</e></URL> inline link.</p></r>'
			],
			[
				'This is [an example](http://example.com/ (Link title)) inline link.',
				'<r><p>This is <URL title="Link title" url="http://example.com/"><s>[</s>an example<e>](http://example.com/ (Link title))</e></URL> inline link.</p></r>'
			],
			[
				'This is [an example](http://example.com/ ""Link title"") inline link.',
				'<r><p>This is <URL title="&quot;Link title&quot;" url="http://example.com/"><s>[</s>an example<e>](http://example.com/ ""Link title"")</e></URL> inline link.</p></r>'
			],
			[
				'.. [link](http://example.com/ ")") ..',
				'<r><p>.. <URL title=")" url="http://example.com/"><s>[</s>link<e>](http://example.com/ ")")</e></URL> ..</p></r>'
			],
			[
				'.. [link](http://example.com/ "") ..',
				'<r><p>.. <URL url="http://example.com/"><s>[</s>link<e>](http://example.com/ "")</e></URL> ..</p></r>'
			],
			[
				'.. [link](http://example.com/ "0") ..',
				'<r><p>.. <URL title="0" url="http://example.com/"><s>[</s>link<e>](http://example.com/ "0")</e></URL> ..</p></r>'
			],
			[
				'.. [link](http://example.com/ "Link title") ..',
				'<r><p>.. <URL title="Link title" url="http://example.com/"><s>[</s>link<e>](http://example.com/ "Link title")</e></URL> ..</p></r>'
			],
			[
				".. [link](http://example.com/ 'Link title') ..",
				'<r><p>.. <URL title="Link title" url="http://example.com/"><s>[</s>link<e>](http://example.com/ \'Link title\')</e></URL> ..</p></r>'
			],
			[
				'[not a link]',
				'<t><p>[not a link]</p></t>'
			],
			[
				'.. [..](http://example.org/foo_(bar)) ..',
				'<r><p>.. <URL url="http://example.org/foo_%28bar%29"><s>[</s>..<e>](http://example.org/foo_(bar))</e></URL> ..</p></r>'
			],
			[
				'.. [..](http://example.org/foo_(bar)_baz) ..',
				'<r><p>.. <URL url="http://example.org/foo_%28bar%29_baz"><s>[</s>..<e>](http://example.org/foo_(bar)_baz)</e></URL> ..</p></r>'
			],
			[
				'[b](https://en.wikipedia.org/wiki/B) [b]..[/b]',
				'<r><p><URL url="https://en.wikipedia.org/wiki/B"><s>[</s>b<e>](https://en.wikipedia.org/wiki/B)</e></URL> <STRONG><s>[b]</s>..<e>[/b]</e></STRONG></p></r>',
				[],
				function ($configurator)
				{
					$configurator->BBCodes->add('B')->tagName = 'STRONG';
				}
			],
			[
				'[](http://example.org/)',
				'<r><p><URL url="http://example.org/"><s>[</s><e>](http://example.org/)</e></URL></p></r>'
			],
			[
				'[[foo]](http://example.org/) [[foo]](http://example.org/)',
				'<r><p><URL url="http://example.org/"><s>[</s>[foo]<e>](http://example.org/)</e></URL> <URL url="http://example.org/"><s>[</s>[foo]<e>](http://example.org/)</e></URL></p></r>'
			],
			[
				'[](http://example.org/?a=1&b=1)[](http://example.org/?a=1&amp;b=1)',
				'<r><p><URL url="http://example.org/?a=1&amp;b=1"><s>[</s><e>](http://example.org/?a=1&amp;b=1)</e></URL><URL url="http://example.org/?a=1&amp;amp;b=1"><s>[</s><e>](http://example.org/?a=1&amp;amp;b=1)</e></URL></p></r>'
			],
			[
				'[](http://example.org/?a=1&b=1)[](http://example.org/?a=1&amp;b=1)',
				'<r><p><URL url="http://example.org/?a=1&amp;b=1"><s>[</s><e>](http://example.org/?a=1&amp;b=1)</e></URL><URL url="http://example.org/?a=1&amp;b=1"><s>[</s><e>](http://example.org/?a=1&amp;amp;b=1)</e></URL></p></r>',
				['decodeHtmlEntities' => true]
			],
			[
				'[x](x "\\a\\b\\\\\\c\\*\\`")',
				'<r><p><URL title="\\a\\b\\\\c*`" url="x"><s>[</s>x<e>](x "\\a\\b\\\\\\c\\*\\`")</e></URL></p></r>'
			],
			[
				'[x](x "foo \\"bar\\"")',
				'<r><p><URL title="foo &quot;bar&quot;" url="x"><s>[</s>x<e>](x "foo \\"bar\\"")</e></URL></p></r>'
			],
			[
				"[x](x 'foo \\'bar\\'')",
				"<r><p><URL title=\"foo 'bar'\" url=\"x\"><s>[</s>x<e>](x 'foo \\'bar\\'')</e></URL></p></r>"
			],
			[
				'[x](x (foo \\(bar\\)))',
				'<r><p><URL title="foo (bar)" url="x"><s>[</s>x<e>](x (foo \\(bar\\)))</e></URL></p></r>'
			],
			[
				[
					'[x](x "a',
					'b")'
				],
				[
					'<r><p><URL title="a&#10;b" url="x"><s>[</s>x<e>](x "a',
					'b")</e></URL></p></r>'
				]
			],
			[
				[
					'> [x](x "a',
					'> b")'
				],
				[
					'<r><QUOTE><i>&gt; </i><p><URL title="a&#10;b" url="x"><s>[</s>x<e>](x "a',
					'&gt; b")</e></URL></p></QUOTE></r>'
				]
			],
			[
				'[..]( http://example.org )',
				'<r><p><URL url="http://example.org"><s>[</s>..<e>]( http://example.org )</e></URL></p></r>'
			],
			// Reference links
			[
				[
					'[foo][1]',
					'',
					' [1]: http://example.org'
				],
				[
					'<r><p><URL url="http://example.org"><s>[</s>foo<e>][1]</e></URL></p>',
					'',
					'<i> [1]: http://example.org</i></r>'
				]
			],
			[
				[
					'> [foo][1]',
					'>',
					'> [1]: http://example.org'
				],
				[
					'<r><QUOTE><i>&gt; </i><p><URL url="http://example.org"><s>[</s>foo<e>][1]</e></URL></p>',
					'<i>&gt;</i>',
					'<i>&gt; [1]: http://example.org</i></QUOTE></r>'
				]
			],
			[
				[
					'> [foo][1]',
					'>',
					'> [1]: http://example.org',
					'',
					'[foo][1]',
					'',
					'[1]: http://example.com'
				],
				[
					'<r><QUOTE><i>&gt; </i><p><URL url="http://example.org"><s>[</s>foo<e>][1]</e></URL></p>',
					'<i>&gt;</i>',
					'<i>&gt; [1]: http://example.org</i></QUOTE>',
					'',
					'<p><URL url="http://example.org"><s>[</s>foo<e>][1]</e></URL></p>',
					'',
					'<i>[1]: http://example.com</i></r>'
				]
			],
			[
				[
					'[foo][1]',
					'',
					'[1]: http://example.org'
				],
				[
					'<r><p><URL url="http://example.org"><s>[</s>foo<e>][1]</e></URL></p>',
					'',
					'<i>[1]: http://example.org</i></r>'
				]
			],
			[
				[
					'[foo] [1]',
					'',
					'[1]: http://example.org'
				],
				[
					'<r><p><URL url="http://example.org"><s>[</s>foo<e>] [1]</e></URL></p>',
					'',
					'<i>[1]: http://example.org</i></r>'
				]
			],
			[
				[
					'[foo][1]',
					'',
					'[1]: http://example.org "Title goes here"'
				],
				[
					'<r><p><URL title="Title goes here" url="http://example.org"><s>[</s>foo<e>][1]</e></URL></p>',
					'',
					'<i>[1]: http://example.org "Title goes here"</i></r>'
				]
			],
			[
				[
					'[foo][1]',
					'',
					'[1]: http://example.org "\\"Title goes here\\""'
				],
				[
					'<r><p><URL title="&quot;Title goes here&quot;" url="http://example.org"><s>[</s>foo<e>][1]</e></URL></p>',
					'',
					'<i>[1]: http://example.org "\\"Title goes here\\""</i></r>'
				]
			],
			[
				[
					'[foo] bar',
					'',
					'[foo]: http://example.org'
				],
				[
					'<r><p><URL url="http://example.org"><s>[</s>foo<e>]</e></URL> bar</p>',
					'',
					'<i>[foo]: http://example.org</i></r>'
				]
			],
			[
				[
					'[Foo] bar',
					'',
					'[foo]: http://example.org'
				],
				[
					'<r><p><URL url="http://example.org"><s>[</s>Foo<e>]</e></URL> bar</p>',
					'',
					'<i>[foo]: http://example.org</i></r>'
				]
			],
			[
				[
					'[foo] bar',
					'',
					'[Foo]: http://example.org'
				],
				[
					'<r><p><URL url="http://example.org"><s>[</s>foo<e>]</e></URL> bar</p>',
					'',
					'<i>[Foo]: http://example.org</i></r>'
				]
			],
			[
				[
					'[foo] bar',
					'',
					'[foo]: http://example.org',
					'[foo]: http://example.com'
				],
				[
					'<r><p><URL url="http://example.org"><s>[</s>foo<e>]</e></URL> bar</p>',
					'',
					'<i>[foo]: http://example.org',
					'[foo]: http://example.com</i></r>'
				]
			],
			[
				// http://stackoverflow.com/a/20885980
				'[//]: # (This may be the most platform independent comment)',
				'<r><i>[//]: # (This may be the most platform independent comment)</i></r>'
			],
			[
				'[center][text](/url)[/center]',
				'<r><CENTER><s>[center]</s><p><URL url="/url"><s>[</s>text<e>](/url)</e></URL></p><e>[/center]</e></CENTER></r>',
				[],
				function ($configurator)
				{
					$configurator->BBCodes->addFromRepository('center');
				}
			],
			[
				[
					'[foo](/bar)',
					'',
					'[foo]: /baz'
				],
				[
					'<r><p><URL url="/bar"><s>[</s>foo<e>](/bar)</e></URL></p>',
					'',
					'<i>[foo]: /baz</i></r>'
				]
			],
			[
				[
					'[b]bold[/b]',
					'',
					'[b]: /foo'
				],
				[
					'<r><p><B><s>[b]</s>bold<e>[/b]</e></B></p>',
					'',
					'<i>[b]: /foo</i></r>'
				],
				[],
				function ($configurator)
				{
					$configurator->BBCodes->addFromRepository('b');
				}
			],
			[
				[
					'[foo][1]',
					'',
					'[1]:  http://example.org  "Title" '
				],
				[
					'<r><p><URL title="Title" url="http://example.org"><s>[</s>foo<e>][1]</e></URL></p>',
					'',
					'<i>[1]:  http://example.org  "Title" </i></r>'
				]
			],
			// Images
			[
				'.. ![Alt text](http://example.org/img.png) ..',
				'<r><p>.. <IMG alt="Alt text" src="http://example.org/img.png"><s>![</s>Alt text<e>](http://example.org/img.png)</e></IMG> ..</p></r>'
			],
			[
				'.. ![Alt text](http://example.org/img.png "Image title") ..',
				'<r><p>.. <IMG alt="Alt text" src="http://example.org/img.png" title="Image title"><s>![</s>Alt text<e>](http://example.org/img.png "Image title")</e></IMG> ..</p></r>'
			],
			[
				".. ![Alt text](http://example.org/img.png 'Image title') ..",
				'<r><p>.. <IMG alt="Alt text" src="http://example.org/img.png" title="Image title"><s>![</s>Alt text<e>](http://example.org/img.png \'Image title\')</e></IMG> ..</p></r>'
			],
			[
				'.. ![Alt text](http://example.org/img.png (Image title)) ..',
				'<r><p>.. <IMG alt="Alt text" src="http://example.org/img.png" title="Image title"><s>![</s>Alt text<e>](http://example.org/img.png (Image title))</e></IMG> ..</p></r>'
			],
			[
				'.. ![Alt \\[text\\]](http://example.org/img.png "\\"Image title\\"") ..',
				'<r><p>.. <IMG alt="Alt [text]" src="http://example.org/img.png" title="&quot;Image title&quot;"><s>![</s>Alt \\[text\\]<e>](http://example.org/img.png "\\"Image title\\"")</e></IMG> ..</p></r>'
			],
			[
				'.. ![Alt text](http://example.org/img.png "Image (title)") ..',
				'<r><p>.. <IMG alt="Alt text" src="http://example.org/img.png" title="Image (title)"><s>![</s>Alt text<e>](http://example.org/img.png "Image (title)")</e></IMG> ..</p></r>'
			],
			[
				'.. ![](http://example.org/img.png) ..',
				'<r><p>.. <IMG alt="" src="http://example.org/img.png"><s>![</s><e>](http://example.org/img.png)</e></IMG> ..</p></r>'
			],
			[
				'.. ![[foo]](http://example.org/img.png) ..',
				'<r><p>.. <IMG alt="[foo]" src="http://example.org/img.png"><s>![</s>[foo]<e>](http://example.org/img.png)</e></IMG> ..</p></r>'
			],
			[
				[
					'![alt\\',
					'text](foo.png)'
				],
				[
					'<r><p><IMG alt="alt\\&#10;text" src="foo.png"><s>![</s>alt\\',
					'text<e>](foo.png)</e></IMG></p></r>'
				]
			],
			[
				[
					'![alt](foo.png "line1',
					'line2")'
				],
				[
					'<r><p><IMG alt="alt" src="foo.png" title="line1&#10;line2"><s>![</s>alt<e>](foo.png "line1',
					'line2")</e></IMG></p></r>'
				]
			],
			[
				'![]( http://example.org/img.png "Title" )',
				'<r><p><IMG alt="" src="http://example.org/img.png" title="Title"><s>![</s><e>]( http://example.org/img.png "Title" )</e></IMG></p></r>'
			],
			// Images in links
			[
				'.. [![Alt text](http://example.org/img.png)](http://example.org/) ..',
				'<r><p>.. <URL url="http://example.org/"><s>[</s><IMG alt="Alt text" src="http://example.org/img.png"><s>![</s>Alt text<e>](http://example.org/img.png)</e></IMG><e>](http://example.org/)</e></URL> ..</p></r>'
			],
			// Reference-style images
			[
				[
					'![][1]',
					'',
					'[1]: http://example.org/img.png'
				],
				[
					'<r><p><IMG alt="" src="http://example.org/img.png"><s>![</s><e>][1]</e></IMG></p>',
					'',
					'<i>[1]: http://example.org/img.png</i></r>'
				]
			],
			[
				[
					'![][1] ![][2] ![][1]',
					'',
					'[1]: http://example.org/img.png'
				],
				[
					'<r><p><IMG alt="" src="http://example.org/img.png"><s>![</s><e>][1]</e></IMG> ![][2] <IMG alt="" src="http://example.org/img.png"><s>![</s><e>][1]</e></IMG></p>',
					'',
					'<i>[1]: http://example.org/img.png</i></r>'
				]
			],
			[
				[
					'![][1]',
					'',
					'[1]: http://example.org/img.png "Title goes there"'
				],
				[
					'<r><p><IMG alt="" src="http://example.org/img.png" title="Title goes there"><s>![</s><e>][1]</e></IMG></p>',
					'',
					'<i>[1]: http://example.org/img.png "Title goes there"</i></r>'
				]
			],
			[
				[
					'![][1]',
					'',
					"[1]: http://example.org/img.png 'Title goes there'"
				],
				[
					'<r><p><IMG alt="" src="http://example.org/img.png" title="Title goes there"><s>![</s><e>][1]</e></IMG></p>',
					'',
					"<i>[1]: http://example.org/img.png 'Title goes there'</i></r>"
				]
			],
			[
				[
					'![][1]',
					'',
					"[1]: http://example.org/img.png (Title goes there)"
				],
				[
					'<r><p><IMG alt="" src="http://example.org/img.png" title="Title goes there"><s>![</s><e>][1]</e></IMG></p>',
					'',
					"<i>[1]: http://example.org/img.png (Title goes there)</i></r>"
				]
			],
			[
				[
					'... ![1] ...',
					'',
					'[1]: http://example.org/img.png'
				],
				[
					'<r><p>... <IMG alt="1" src="http://example.org/img.png"><s>![</s>1<e>]</e></IMG> ...</p>',
					'',
					'<i>[1]: http://example.org/img.png</i></r>'
				]
			],
			[
				[
					'... ![1][b][/b] ...',
					'',
					'[1]: http://example.org/img.png'
				],
				[
					'<r><p>... <IMG alt="1" src="http://example.org/img.png"><s>![</s>1<e>]</e></IMG>[b][/b] ...</p>',
					'',
					'<i>[1]: http://example.org/img.png</i></r>'
				]
			],
			[
				[
					'... ![1][b][/b] ...',
					'',
					'[1]: http://example.org/img.png',
					'[b]: http://example.org/b.png'
				],
				[
					'<r><p>... <IMG alt="1" src="http://example.org/b.png"><s>![</s>1<e>][b]</e></IMG>[/b] ...</p>',
					'',
					'<i>[1]: http://example.org/img.png',
					'[b]: http://example.org/b.png</i></r>'
				]
			],
			[
				[
					'![Alt text][1]',
					'',
					'[1]: http://example.org/img.png'
				],
				[
					'<r><p><IMG alt="Alt text" src="http://example.org/img.png"><s>![</s>Alt text<e>][1]</e></IMG></p>',
					'',
					'<i>[1]: http://example.org/img.png</i></r>'
				]
			],
			[
				[
					'[![][1]][1]',
					'',
					'[1]: http://example.org/img.png'
				],
				[
					'<r><p><URL url="http://example.org/img.png"><s>[</s><IMG alt="" src="http://example.org/img.png"><s>![</s><e>][1]</e></IMG><e>][1]</e></URL></p>',
					'',
					'<i>[1]: http://example.org/img.png</i></r>'
				]
			],
			[
				[
					'[![1]][1]',
					'',
					'[1]: http://example.org/img.png'
				],
				[
					'<r><p><URL url="http://example.org/img.png"><s>[</s><IMG alt="1" src="http://example.org/img.png"><s>![</s>1<e>]</e></IMG><e>][1]</e></URL></p>',
					'',
					'<i>[1]: http://example.org/img.png</i></r>'
				]
			],
			// Inline code
			[
				'.. `foo` `bar` ..',
				'<r><p>.. <C><s>`</s>foo<e>`</e></C> <C><s>`</s>bar<e>`</e></C> ..</p></r>'
			],
			[
				'.. `foo `` bar` ..',
				'<r><p>.. <C><s>`</s>foo `` bar<e>`</e></C> ..</p></r>'
			],
			[
				'.. `foo ``` bar` ..',
				'<r><p>.. <C><s>`</s>foo ``` bar<e>`</e></C> ..</p></r>'
			],
			[
				'.. ``foo`` ``bar`` ..',
				'<r><p>.. <C><s>``</s>foo<e>``</e></C> <C><s>``</s>bar<e>``</e></C> ..</p></r>'
			],
			[
				'.. ``foo `bar` baz`` ..',
				'<r><p>.. <C><s>``</s>foo `bar` baz<e>``</e></C> ..</p></r>'
			],
			[
				'`\\`',
				'<r><p><C><s>`</s>\\<e>`</e></C></p></r>'
			],
			[
				'\\``\\`',
				'<r><p>\\`<C><s>`</s>\\<e>`</e></C></p></r>'
			],
			[
				'.. ` x\\`` ` ..',
				'<r><p>.. <C><s>` </s>x\``<e> `</e></C> ..</p></r>'
			],
			[
				'`x` \\` `\\`',
				'<r><p><C><s>`</s>x<e>`</e></C> \\` <C><s>`</s>\\<e>`</e></C></p></r>'
			],
			[
				'.. `[foo](http://example.org)` ..',
				'<r><p>.. <C><s>`</s>[foo](http://example.org)<e>`</e></C> ..</p></r>'
			],
			[
				'.. `![foo](http://example.org)` ..',
				'<r><p>.. <C><s>`</s>![foo](http://example.org)<e>`</e></C> ..</p></r>'
			],
			[
				'.. `x` ..',
				'<r><p>.. <C><s>`</s>x<e>`</e></C> ..</p></r>'
			],
			[
				'.. ``x`` ..',
				'<r><p>.. <C><s>``</s>x<e>``</e></C> ..</p></r>'
			],
			[
				'.. ```x``` ..',
				'<r><p>.. <C><s>```</s>x<e>```</e></C> ..</p></r>'
			],
			[
				"`foo\nbar`",
				"<r><p><C><s>`</s>foo\nbar<e>`</e></C></p></r>"
			],
			[
				"`foo\n\nbar`",
				"<t><p>`foo</p>\n\n<p>bar`</p></t>"
			],
			[
				'```code```',
				'<r><p><C><s>```</s>code<e>```</e></C></p></r>'
			],
			[
				'``` code ```',
				'<r><p><C><s>``` </s>code<e> ```</e></C></p></r>'
			],
			[
				'``` co````de ```',
				'<r><p><C><s>``` </s>co````de<e> ```</e></C></p></r>'
			],
			[
				'``` ```',
				'<r><p><C><s>``` </s><e>```</e></C></p></r>'
			],
			[
				'``` `` ```',
				'<r><p><C><s>``` </s>``<e> ```</e></C></p></r>'
			],
			[
				'` `` `',
				'<r><p><C><s>` </s>``<e> `</e></C></p></r>'
			],
			[
				'``` x ``',
				'<t><p>``` x ``</p></t>'
			],
			[
				'x ``` x ``',
				'<t><p>x ``` x ``</p></t>'
			],
			// Strikethrough
			[
				'.. ~~foo~~ ~~bar~~ ..',
				'<r><p>.. <DEL><s>~~</s>foo<e>~~</e></DEL> <DEL><s>~~</s>bar<e>~~</e></DEL> ..</p></r>'
			],
			[
				'.. ~~foo~bar~~ ..',
				'<r><p>.. <DEL><s>~~</s>foo~bar<e>~~</e></DEL> ..</p></r>'
			],
			[
				'.. ~~foo\\~~ ~~bar~~ ..',
				'<r><p>.. <DEL><s>~~</s>foo\\~~ <e>~~</e></DEL>bar~~ ..</p></r>'
			],
			[
				'.. ~~~~ ..',
				'<t><p>.. ~~~~ ..</p></t>'
			],
			[
				"~~foo\nbar~~",
				"<r><p><DEL><s>~~</s>foo\nbar<e>~~</e></DEL></p></r>"
			],
			[
				"~~foo\n\nbar~~",
				"<t><p>~~foo</p>\n\n<p>bar~~</p></t>"
			],
			// Superscript
			[
				'.. foo^baar^baz 1^2 ..',
				'<r><p>.. foo<SUP><s>^</s>baar<SUP><s>^</s>baz</SUP></SUP> 1<SUP><s>^</s>2</SUP> ..</p></r>'
			],
			[
				'.. \\^_^ ..',
				'<t><p>.. \^_^ ..</p></t>'
			],
			// Emphasis
			[
				'xx ***x*****x** xx',
				'<r><p>xx <STRONG><s>**</s><EM><s>*</s>x<e>*</e></EM><e>**</e></STRONG><STRONG><s>**</s>x<e>**</e></STRONG> xx</p></r>'
			],
			[
				'xx ***x****x* xx',
				'<r><p>xx <STRONG><s>**</s><EM><s>*</s>x<e>*</e></EM><e>**</e></STRONG><EM><s>*</s>x<e>*</e></EM> xx</p></r>'
			],
			[
				'xx ***x*** xx',
				'<r><p>xx <STRONG><s>**</s><EM><s>*</s>x<e>*</e></EM><e>**</e></STRONG> xx</p></r>'
			],
			[
				'***strongem*strong***em*',
				'<r><p><STRONG><s>**</s><EM><s>*</s>strongem<e>*</e></EM>strong<e>**</e></STRONG><EM><s>*</s>em<e>*</e></EM></p></r>'
			],
			[
				'***emstrong**em***strong**',
				'<r><p><EM><s>*</s><STRONG><s>**</s>emstrong<e>**</e></STRONG>em<e>*</e></EM><STRONG><s>**</s>strong<e>**</e></STRONG></p></r>'
			],
			[
				'xx **x*****x*** xx',
				'<r><p>xx <STRONG><s>**</s>x<e>**</e></STRONG><STRONG><s>**</s><EM><s>*</s>x<e>*</e></EM><e>**</e></STRONG> xx</p></r>'
			],
			[
				'xx **x****x** xx',
				'<r><p>xx <STRONG><s>**</s>x<e>**</e></STRONG><STRONG><s>**</s>x<e>**</e></STRONG> xx</p></r>'
			],
			[
				'xx **x***x* xx',
				'<r><p>xx <STRONG><s>**</s>x<e>**</e></STRONG><EM><s>*</s>x<e>*</e></EM> xx</p></r>'
			],
			[
				'xx **x** xx',
				'<r><p>xx <STRONG><s>**</s>x<e>**</e></STRONG> xx</p></r>'
			],
			[
				'xx **x*x** xx',
				'<r><p>xx <STRONG><s>**</s>x*x<e>**</e></STRONG> xx</p></r>'
			],
			[
				'xx *x*****x*** xx',
				'<r><p>xx <EM><s>*</s>x<e>*</e></EM>*<STRONG><s>**</s><EM><s>*</s>x<e>*</e></EM><e>**</e></STRONG> xx</p></r>'
			],
			[
				'xx *x****x*** xx',
				'<r><p>xx <EM><s>*</s>x<e>*</e></EM><STRONG><s>**</s><EM><s>*</s>x<e>*</e></EM><e>**</e></STRONG> xx</p></r>'
			],
			[
				'xx *x**x* xx',
				'<r><p>xx <EM><s>*</s>x**x<e>*</e></EM> xx</p></r>'
			],
			[
				'xx *x* xx',
				'<r><p>xx <EM><s>*</s>x<e>*</e></EM> xx</p></r>'
			],
			[
				'xx *x**x*x** xx',
				'<r><p>xx <EM><s>*</s>x<STRONG><s>**</s>x</STRONG><e>*</e></EM><STRONG>x<e>**</e></STRONG> xx</p></r>'
			],
			[
				"*foo\nbar*",
				"<r><p><EM><s>*</s>foo\nbar<e>*</e></EM></p></r>"
			],
			[
				"*foo\n\nbar*",
				"<t><p>*foo</p>\n\n<p>bar*</p></t>"
			],
			[
				"***foo*\n\nbar**",
				"<r><p>**<EM><s>*</s>foo<e>*</e></EM></p>\n\n<p>bar**</p></r>"
			],
			[
				"***foo**\n\nbar*",
				"<r><p>*<STRONG><s>**</s>foo<e>**</e></STRONG></p>\n\n<p>bar*</p></r>"
			],
			[
				'xx _x_ xx',
				'<r><p>xx <EM><s>_</s>x<e>_</e></EM> xx</p></r>'
			],
			[
				'xx __x__ xx',
				'<r><p>xx <STRONG><s>__</s>x<e>__</e></STRONG> xx</p></r>'
			],
			[
				'xx foo_bar_baz xx',
				'<t><p>xx foo_bar_baz xx</p></t>'
			],
			[
				'xx foo__bar__baz xx',
				'<r><p>xx foo<STRONG><s>__</s>bar<e>__</e></STRONG>baz xx</p></r>'
			],
			[
				'x _foo_',
				'<r><p>x <EM><s>_</s>foo<e>_</e></EM></p></r>'
			],
			[
				'_foo_ x',
				'<r><p><EM><s>_</s>foo<e>_</e></EM> x</p></r>'
			],
			[
				'_foo_',
				'<r><p><EM><s>_</s>foo<e>_</e></EM></p></r>'
			],
			[
				'xx ***x******x*** xx',
				'<r><p>xx <STRONG><s>**</s><EM><s>*</s>x<e>*</e></EM><e>**</e></STRONG><STRONG><s>**</s><EM><s>*</s>x<e>*</e></EM><e>**</e></STRONG> xx</p></r>'
			],
			[
				'xx ***x*******x*** xx',
				'<r><p>xx <STRONG><s>**</s><EM><s>*</s>x<e>*</e></EM><e>**</e></STRONG>*<STRONG><s>**</s><EM><s>*</s>x<e>*</e></EM><e>**</e></STRONG> xx</p></r>'
			],
			[
				'xx *****x***** xx',
				'<r><p>xx **<STRONG><s>**</s><EM><s>*</s>x<e>*</e></EM><e>**</e></STRONG>** xx</p></r>'
			],
			[
				'xx **x*x*** xx',
				'<r><p>xx <STRONG><s>**</s>x<EM><s>*</s>x<e>*</e></EM><e>**</e></STRONG> xx</p></r>'
			],
			[
				'xx *x**x*** xx',
				'<r><p>xx <EM><s>*</s>x<STRONG><s>**</s>x<e>**</e></STRONG><e>*</e></EM> xx</p></r>'
			],
			[
				'\\\\*foo*',
				'<r><p>\\\\<EM><s>*</s>foo<e>*</e></EM></p></r>'
			],
			[
				'*\\\\*foo*',
				'<r><p><EM><s>*</s>\\\\<e>*</e></EM>foo*</p></r>'
			],
			[
				'*x *x *x',
				'<t><p>*x *x *x</p></t>'
			],
			[
				'x* x* x*',
				'<t><p>x* x* x*</p></t>'
			],
			[
				'*x x* x*',
				'<r><p><EM><s>*</s>x x<e>*</e></EM> x*</p></r>'
			],
			[
				'*x *x x*',
				'<r><p>*x <EM><s>*</s>x x<e>*</e></EM></p></r>'
			],
			[
				'*x **x** x*',
				'<r><p><EM><s>*</s>x <STRONG><s>**</s>x<e>**</e></STRONG> x<e>*</e></EM></p></r>'
			],
			[
				'x * x * x',
				'<t><p>x * x * x</p></t>'
			],
			[
				'x * x*x * x',
				'<t><p>x * x*x * x</p></t>'
			],
			[
				"*x\nx*",
				"<r><p><EM><s>*</s>x\nx<e>*</e></EM></p></r>"
			],
			[
				"_\nx_",
				"<t><p>_\nx_</p></t>"
			],
			// Forced line breaks
			[
				[
					'first line  ',
					'second line  ',
					'third line'
				],
				[
					'<t><p>first line  <br/>',
					'second line  <br/>',
					'third line</p></t>'
				],
			],
			[
				[
					'first line  ',
					'second line  '
				],
				[
					'<t><p>first line  <br/>',
					'second line</p>  </t>'
				],
			],
			[
				[
					'> first line  ',
					'> second line  ',
					'',
					'outside quote'
				],
				[
					'<r><QUOTE><i>&gt; </i><p>first line  <br/>',
					'<i>&gt; </i>second line</p>  </QUOTE>',
					'',
					'<p>outside quote</p></r>'
				],
			],
			[
				[
					'    first line  ',
					'    second line  ',
					'',
					'outside code'
				],
				[
					'<r><i>    </i><CODE>first line  ',
					'<i>    </i>second line  </CODE>',
					'',
					'<p>outside code</p></r>'
				],
			],
			[
				[
					'    first line  ',
					'',
					'outside code'
				],
				[
					'<r><i>    </i><CODE>first line  </CODE>',
					'',
					'<p>outside code</p></r>'
				],
			],
			[
				[
					' * first item  ',
					'   still the first item  ',
					' * second item',
					'',
					'outside list'
				],
				[
					'<r> <LIST><LI><s>* </s>first item  <br/>',
					'   still the first item  </LI>',
					' <LI><s>* </s>second item</LI></LIST>',
					'',
					'<p>outside list</p></r>'
				],
			],
			[
				[
					'foo  ',
					'---  ',
					'bar  '
				],
				[
					'<r><H2>foo<e>  ',
					'---  </e></H2>',
					'<p>bar</p>  </r>'
				]
			],
		]);
	}

	public function getRenderingTests()
	{
		return self::fixTests([
			[
				'> foo',
				'<blockquote><p>foo</p></blockquote>'
			],
			[
				[
					'> > foo',
					'> ',
					'> bar',
					'',
					'baz'
				],
				[
					'<blockquote><blockquote><p>foo</p></blockquote>',
					'',
					'<p>bar</p></blockquote>',
					'',
					'<p>baz</p>'
				]
			],
			[
				[
					'foo',
					'',
					'## bar',
					'',
					'baz'
				],
				[
					'<p>foo</p>',
					'',
					'<h2>bar</h2>',
					'',
					'<p>baz</p>'
				]
			],
			[
				[
					'* 0',
					' * 1',
					'  * 2',
					'   * 3',
					'    * 4',
					'     * 5',
					'      * 6',
					'       * 7',
					'        * 8',
					'         * 9'
				],
				[
					'<ul><li>0',
					' <ul><li>1</li>',
					'  <li>2</li>',
					'   <li>3</li>',
					'    <li>4',
					'     <ul><li>5</li>',
					'      <li>6</li>',
					'       <li>7</li>',
					'        <li>8',
					'         <ul><li>9</li></ul></li></ul></li></ul></li></ul>'
				]
			],
			[
				[
					'1. one',
					'2. two'
				],
				[
					'<ol><li>one</li>',
					'<li>two</li></ol>'
				]
			],
			[
				[
					' 21. twenty-one',
					' 22. twenty-two'
				],
				[
					' <ol start="21"><li>twenty-one</li>',
					' <li>twenty-two</li></ol>'
				]
			],
			[
				[
					'- one',
					'  - foo',
					'  - bar',
					'',
					'- two',
					'  - bar',
					'  - baz',
					'',
					'- three'
				],
				[
					'<ul><li><p>one</p>',
					'  <ul><li>foo</li>',
					'  <li>bar</li></ul></li>',
					'',
					'<li><p>two</p>',
					'  <ul><li>bar</li>',
					'  <li>baz</li></ul></li>',
					'',
					'<li><p>three</p></li></ul>'
				],
			],
			[
				'[Link text](http://example.org)',
				'<p><a href="http://example.org">Link text</a></p>'
			],
			[
				'[Link text](http://example.org "Link title")',
				'<p><a href="http://example.org" title="Link title">Link text</a></p>'
			],
			[
				[
					'```',
					'code',
					'```'
				],
				'<pre><code>code</code></pre>'
			],
			[
				[
					'```html',
					'code',
					'```'
				],
				'<pre><code class="language-html">code</code></pre>'
			],
			[
				[
					'![alt',
					'text](img)'
				],
				[
					'<p><img src="img" alt="alt',
					'text"></p>'
				]
			],
		]);
	}

	protected static function fixTests($tests)
	{
		foreach ($tests as &$test)
		{
			if (is_array($test[0]))
			{
				$test[0] = implode("\n", $test[0]);
			}

			if (is_array($test[1]))
			{
				$test[1] = implode("\n", $test[1]);
			}

			if (!isset($test[2]))
			{
				$test[2] = [];
			}

			$callback = (isset($test[3])) ? $test[3] : null;
			$test[3] = function ($configurator) use ($callback)
			{
				if (isset($callback))
				{
					$callback($configurator);
				}
			};
		}

		return $tests;
	}
}