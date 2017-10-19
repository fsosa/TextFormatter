<?php

namespace s9e\TextFormatter\Tests\Plugins\MediaEmbed;

use s9e\TextFormatter\Configurator;
use s9e\TextFormatter\Parser\Tag;
use s9e\TextFormatter\Plugins\MediaEmbed\Parser;
use s9e\TextFormatter\Tests\Plugins\ParsingTestsRunner;
use s9e\TextFormatter\Tests\Plugins\ParsingTestsJavaScriptRunner;
use s9e\TextFormatter\Tests\Plugins\RenderingTestsRunner;
use s9e\TextFormatter\Tests\Test;

/**
* @covers s9e\TextFormatter\Plugins\MediaEmbed\Parser
*/
class ParserTest extends Test
{
	use ParsingTestsRunner;
	use ParsingTestsJavaScriptRunner;
	use RenderingTestsRunner;

	protected static function populateCache($entries)
	{
		$cacheDir = __DIR__ . '/../../.cache';

		if (!file_exists($cacheDir))
		{
			$cacheDir = sys_get_temp_dir();
		}

		$prefix = $suffix = '';
		if (extension_loaded('zlib'))
		{
			$prefix  = 'compress.zlib://';
			$suffix  = '.gz';
		}

		foreach ($entries as $url => $content)
		{
			file_put_contents(
				$prefix . $cacheDir . '/http.' . crc32($url) . $suffix,
				$content
			);
		}

		return $cacheDir;
	}

	/**
	* Run a test that involves scraping without causing a fail if the remote server errors out
	*/
	protected function runScrapingTest($methodName, $args)
	{
		try
		{
			call_user_func_array([$this, $methodName], $args);
		}
		catch (\PHPUnit_Framework_Error_Warning $e)
		{
			$msg = $e->getMessage();

			if (strpos($msg, 'HTTP request failed')  !== false
			 || strpos($msg, 'Connection timed out') !== false
			 || strpos($msg, 'Connection refused')   !== false)
			{
				$this->markTestSkipped($msg);
			}

			throw $e;
		}
	}

	/**
	* @testdox scrape() does not do anything if the tag does not have a "url" attribute
	*/
	public function testScrapeNoUrl()
	{
		$tag = new Tag(Tag::START_TAG, 'MEDIA', 0, 0);

		$this->assertTrue(Parser::scrape($tag, []));
	}

	/**
	* @testdox The MEDIA tag transfers its priority to the tag it creates
	*/
	public function testTagPriority()
	{
		$newTag = $this->getMockBuilder('s9e\\TextFormatter\\Parser\\Tag')
		               ->disableOriginalConstructor()
		               ->setMethods(['setAttributes'])
		               ->getMock();

		$tagStack = $this->getMockBuilder('s9e\\TextFormatter\\Parser')
		                 ->disableOriginalConstructor()
		                 ->setMethods(['addTagPair'])
		                 ->getMock();

		$tagStack->expects($this->once())
		         ->method('addTagPair')
		         ->with('FOO', 0, 0, 1, 0, 123)
		         ->will($this->returnValue($newTag));

		$tag = new Tag(Tag::START_TAG, 'MEDIA', 0, 1, 123);
		$tag->setAttribute('site', 'foo');

		Parser::filterTag($tag, $tagStack, ['foo.invalid' => 'foo']);
	}

	/**
	* @testdox The MEDIA tag can be effectively disabled
	*/
	public function testDisableTag()
	{
		$this->configurator->MediaEmbed->add(
			'foo',
			[
				'host'    => 'localhost',
				'extract' => "!localhost/video/(?'id'\\d+)!",
				'iframe'  => ['src' => '//localhost/embed/{@id}']
			]
		);

		$text = 'http://localhost/video/123';
		$parser = $this->getParser();
		$this->assertSame(
			'<r><FOO id="123" url="http://localhost/video/123">http://localhost/video/123</FOO></r>',
			$parser->parse($text)
		);
		$parser->disableTag('MEDIA');
		$this->assertSame(
			'<t>http://localhost/video/123</t>',
			$parser->parse($text)
		);
	}

	/**
	* @testdox Abstract tests (not tied to bundled sites)
	* @dataProvider getAbstractTests
	*/
	public function testAbstract()
	{
		call_user_func_array([$this, 'testParsing'], func_get_args());
	}

	public function getAbstractTests()
	{
		return [
			[
				// Multiple "match" in scrape
				'http://example.invalid/123',
				'<r><EXAMPLE id="456" url="http://example.invalid/123">http://example.invalid/123</EXAMPLE></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = self::populateCache([
						'http://example.invalid/123' => '456'
					]);

					$configurator->MediaEmbed->add(
						'example',
						[
							'host'   => 'example.invalid',
							'scrape' => [
								'match'   => ['/XXX/', '/123/'],
								'extract' => "!^(?'id'[0-9]+)$!"
							],
							'iframe' => [
								'width'  => 560,
								'height' => 315,
								'src'    => '//localhost'
							]
						]
					);
				}
			],
			[
				// Multiple "extract" in scrape
				'http://example.invalid/123',
				'<r><EXAMPLE id="456" url="http://example.invalid/123">http://example.invalid/123</EXAMPLE></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = self::populateCache([
						'http://example.invalid/123' => '456'
					]);

					$configurator->MediaEmbed->add(
						'example',
						[
							'host'   => 'example.invalid',
							'scrape' => [
								'match'   => '/./',
								'extract' => ['/foo/', "!^(?'id'[0-9]+)$!"]
							],
							'iframe' => [
								'width'  => 560,
								'height' => 315,
								'src'    => '//localhost'
							]
						]
					);
				}
			],
			[
				// Multiple scrapes
				'http://example.invalid/123',
				'<r><EXAMPLE id="456" url="http://example.invalid/123">http://example.invalid/123</EXAMPLE></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = self::populateCache([
						'http://example.invalid/123' => '456'
					]);

					$configurator->MediaEmbed->add(
						'example',
						[
							'host'   => 'example.invalid',
							'scrape' => [
								[
									'match'   => '/./',
									'extract' => '/foo/'
								],
								[
									'match'   => '/./',
									'extract' => "!^(?'id'[0-9]+)$!"
								]
							],
							'iframe' => [
								'width'  => 560,
								'height' => 315,
								'src'    => '//localhost'
							]
						]
					);
				}
			],
			[
				// Ensure that the hash is ignored when scraping
				'http://example.invalid/123#hash',
				'<r><EXAMPLE id="success" url="http://example.invalid/123#hash">http://example.invalid/123#hash</EXAMPLE></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = self::populateCache([
						'http://example.invalid/123'      => 'success',
						'http://example.invalid/123#hash' => 'error'
					]);

					$configurator->MediaEmbed->add(
						'example',
						[
							'host'   => 'example.invalid',
							'scrape' => [
								[
									'match'   => '/./',
									'extract' => '/(?<id>\\w+)/'
								],
							],
							'iframe' => []
						]
					);
				}
			],
			[
				// Ensure that non-HTTP URLs don't get scraped
				'[media]invalid://example.org/123[/media]',
				'<t>[media]invalid://example.org/123[/media]</t>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add(
						'example',
						[
							'host'   => 'example.org',
							'scrape' => [
								'match'   => '/./',
								'extract' => "/(?'id'[0-9]+)/"
							],
							'iframe' => ['width' => 1, 'height' => 1, 'src' => '{@id}']
						]
					);
				}
			],
			[
				// Ensure that invalid URLs don't get scraped
				'[media]http://example.invalid/123?x"> foo="bar[/media]',
				'<t>[media]http://example.invalid/123?x"&gt; foo="bar[/media]</t>',
				['captureURLs' => false],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = self::populateCache([
						'http://example.invalid/123?x"> foo="bar' => '456'
					]);

					$configurator->MediaEmbed->add(
						'example',
						[
							'host'   => 'example.invalid',
							'scrape' => [
								'match'   => '/./',
								'extract' => "/(?'id'[0-9]+)/"
							],
							'iframe' => ['width' => 1, 'height' => 1, 'src' => '{@id}']
						]
					);
				}
			],
			[
				// Ensure that we don't scrape the URL if it doesn't match
				'[media]http://example.invalid/123[/media]',
				'<t>[media]http://example.invalid/123[/media]</t>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add(
						'example',
						[
							'host'   => 'example.invalid',
							'scrape' => [
								'match'   => '/XXX/',
								'extract' => "/(?'id'[0-9]+)/"
							],
							'iframe' => ['width' => 1, 'height' => 1, 'src' => '{@id}']
						]
					);
				}
			],
			[
				// Ensure that we don't scrape if the attributes are already filled
				'http://example.invalid/123',
				'<r><EXAMPLE id="12" url="http://example.invalid/123">http://example.invalid/123</EXAMPLE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add(
						'example',
						[
							'host'   => 'example.invalid',
							'extract' => "#/(?'id'[0-9]{2})#",
							'scrape' => [
								'match'   => '/./',
								'extract' => "/(?'id'[0-9]+)/"
							],
							'iframe'  => ['width' => 1, 'height' => 1, 'src' => '{@id}']
						]
					);
				}
			],
			[
				'[media]http://foo.example.org/123[/media]',
				'<r><X2 id="123" url="http://foo.example.org/123"><s>[media]</s>http://foo.example.org/123<e>[/media]</e></X2></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add(
						'x1',
						[
							'host'    => 'example.org',
							'extract'  => "/(?'id'\\d+)/",
							'iframe' => [
								'width'  => 560,
								'height' => 315,
								'src'    => '//localhost'
							]
						]
					);
					$configurator->MediaEmbed->add(
						'x2',
						[
							'host'    => 'foo.example.org',
							'extract'  => "/(?'id'\\d+)/",
							'iframe' => [
								'width'  => 560,
								'height' => 315,
								'src'    => '//localhost'
							]
						]
					);
				}
			],
			[
				// Ensure no bad things(tm) happen when there's no match
				'[media]http://example.org/123[/media]',
				'<t>[media]http://example.org/123[/media]</t>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add(
						'x2',
						[
							'host'    => 'foo.example.org',
							'extract'  => "/(?'id'\\d+)/",
							'iframe' => [
								'width'  => 560,
								'height' => 315,
								'src'    => '//localhost'
							]
						]
					);
				}
			],
			[
				// Test that we don't replace the "id" attribute with an URL
				'[media=foo]http://example.org/123[/media]',
				'<r><FOO id="123" url="http://example.org/123"><s>[media=foo]</s>http://example.org/123<e>[/media]</e></FOO></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add(
						'foo',
						[
							'host'    => 'foo.example.org',
							'extract'  => "/(?'id'\\d+)/",
							'iframe' => [
								'width'  => 560,
								'height' => 315,
								'src'    => '//localhost'
							]
						]
					);
				}
			],
			[
				'[media]http://example.com/baz[/media]',
				'<t>[media]http://example.com/baz[/media]</t>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add(
						'foo',
						[
							'host'    => 'example.com',
							'extract'  => [
								"!example\\.com/(?<foo>foo)!",
								"!example\\.com/(?<bar>bar)!"
							],
							'iframe' => [
								'width'  => 560,
								'height' => 315,
								'src'    => '//localhost'
							]
						]
					);
				}
			],
			[
				'[media]http://example.com/foo[/media]',
				'<r><FOO foo="foo" url="http://example.com/foo"><s>[media]</s>http://example.com/foo<e>[/media]</e></FOO></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add(
						'foo',
						[
							'host'    => 'example.com',
							'extract'  => [
								"!example\\.com/(?<foo>foo)!",
								"!example\\.com/(?<bar>bar)!"
							],
							'iframe' => [
								'width'  => 560,
								'height' => 315,
								'src'    => '//localhost'
							]
						]
					);
				}
			],
			[
				// @bar is invalid, no match == tag is invalidated
				'[foo bar=BAR]http://example.com/baz[/foo]',
				'<t>[foo bar=BAR]http://example.com/baz[/foo]</t>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add(
						'foo',
						[
							'host'    => 'example.com',
							'extract'  => [
								"!example\\.com/(?<foo>foo)!",
								"!example\\.com/(?<bar>bar)!"
							],
							'iframe' => [
								'width'  => 560,
								'height' => 315,
								'src'    => '//localhost'
							]
						]
					);
				}
			],
			[
				// No match on URL but @bar is valid == tag is kept
				'[foo bar=bar]http://example.com/baz[/foo]',
				'<r><FOO bar="bar" url="http://example.com/baz"><s>[foo bar=bar]</s>http://example.com/baz<e>[/foo]</e></FOO></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->createIndividualBBCodes = true;
					$configurator->MediaEmbed->add(
						'foo',
						[
							'host'    => 'example.com',
							'extract'  => [
								"!example\\.com/(?<foo>foo)!",
								"!example\\.com/(?<bar>bar)!"
							],
							'iframe' => [
								'width'  => 560,
								'height' => 315,
								'src'    => '//localhost'
							]
						]
					);
				}
			],
			[
				'[media=x]..[/media]',
				'<t>[media=x]..[/media]</t>',
				[],
				function ($configurator)
				{
					$configurator->BBCodes;
					$configurator->MediaEmbed->add('foo', ['host' => 'example.invalid']);
					$configurator->tags->add('X');
				}
			],
			[
				'http://localhost/video/123',
				'<r><FOO id="123" url="http://localhost/video/123"><URL url="http://localhost/video/123">http://localhost/video/123</URL></FOO></r>',
				[],
				function ($configurator)
				{
					$configurator->Autolink;
					$configurator->MediaEmbed->add(
						'foo',
						[
							'host'    => 'localhost',
							'extract' => "!localhost/video/(?'id'\\d+)!",
							'iframe'  => ['src' => '//localhost/embed/{@id}']
						]
					);
				}
			],
			[
				'HTTP://example.com/123',
				'<r><FOO id="123" url="http://example.com/123">HTTP://example.com/123</FOO></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add(
						'foo',
						[
							'host'    => 'example.com',
							'extract' => '!example\\.com/(?<id>\\d+)!',
							'iframe'  => ['src' => '//localhost/embed/{@id}']
						]
					);
				}
			],
			[
				'[video]http://example.org/123[/video]',
				'<r><EXAMPLE id="123" url="http://example.org/123"><s>[video]</s>http://example.org/123<e>[/video]</e></EXAMPLE></r>',
				[],
				function ($configurator)
				{
					$configurator->plugins->load('MediaEmbed', ['tagName' => 'video'])->add(
						'example',
						[
							'host'    => 'example.org',
							'extract' => "/(?'id'\\d+)/",
							'iframe'  => ['src' => '//localhost']
						]
					);
				}
			],
		];
	}

	/**
	* @testdox Scraping tests
	* @dataProvider getScrapingTests
	* @group needs-network
	*/
	public function testScraping()
	{
		$this->runScrapingTest('testParsing', func_get_args());
	}

	public function getScrapingTests()
	{
		return [
			[
				'http://proleter.bandcamp.com/album/curses-from-past-times-ep',
				'<r><BANDCAMP album_id="1122163921" url="http://proleter.bandcamp.com/album/curses-from-past-times-ep">http://proleter.bandcamp.com/album/curses-from-past-times-ep</BANDCAMP></r>',
				[],
				function ($configurator)
				{
					// Skip during cache preload
					if (isset($_SERVER['TRAVIS']) && isset($_SERVER['CACHE_PRELOAD']))
					{
						$this->markTestSkipped();
					}

					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('bandcamp');
				}
			],
			[
				'http://proleter.bandcamp.com/track/muhammad-ali',
				'<r><BANDCAMP album_id="1122163921" track_id="3496015802" track_num="7" url="http://proleter.bandcamp.com/track/muhammad-ali">http://proleter.bandcamp.com/track/muhammad-ali</BANDCAMP></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('bandcamp');
				}
			],
			[
				'http://therunons.bandcamp.com/track/still-feel',
				'<r><BANDCAMP track_id="2146686782" url="http://therunons.bandcamp.com/track/still-feel">http://therunons.bandcamp.com/track/still-feel</BANDCAMP></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('bandcamp');
				}
			],
//			[
//				'http://www.bbc.com/news/av/entertainment-arts-39741822/gold-darth-vader-mask-up-for-sale',
//				'<r><BBCNEWS id="av/entertainment-arts-39741822/gold-darth-vader-mask-goes-on-sale-in-japan" url="http://www.bbc.com/news/av/entertainment-arts-39741822/gold-darth-vader-mask-up-for-sale">http://www.bbc.com/news/av/entertainment-arts-39741822/gold-darth-vader-mask-up-for-sale</BBCNEWS></r>',
//				[],
//				function ($configurator)
//				{
//					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
//					$configurator->MediaEmbed->add('bbcnews');
//				}
//			],
//			[
//				'http://bleacherreport.com/articles/2415420-creating-a-starting-xi-of-the-most-overrated-players-in-world-football',
//				'<r><BLEACHERREPORT id="dtYjVhdDr5492cyQTjVPDcM--Mg2rJj5" url="http://bleacherreport.com/articles/2415420-creating-a-starting-xi-of-the-most-overrated-players-in-world-football">http://bleacherreport.com/articles/2415420-creating-a-starting-xi-of-the-most-overrated-players-in-world-football</BLEACHERREPORT></r>',
//				[],
//				function ($configurator)
//				{
//					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
//					$configurator->MediaEmbed->add('bleacherreport');
//				}
//			],
			[
				'http://link.brightcove.com/services/player/bcpid4501318026001?bctid=5045373183001',
				'<r><BRIGHTCOVE bckey="AQ~~,AAAB9mw57HE~,xU4DCdZtHhuIakVdyH5VnUosMOtC9a9v" bcpid="2869183374001" bctid="5045373183001" url="http://link.brightcove.com/services/player/bcpid4501318026001?bctid=5045373183001">http://link.brightcove.com/services/player/bcpid4501318026001?bctid=5045373183001</BRIGHTCOVE></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('brightcove');
				}
			],
//			[
//				'http://www.cc.com/video-clips/uu5qz4/key-and-peele-dueling-hats',
//				'<r><COMEDYCENTRAL id="mgid:arc:video:comedycentral.com:bc275e2f-48e3-46d9-b095-0254381497ea" url="http://www.cc.com/video-clips/uu5qz4/key-and-peele-dueling-hats">http://www.cc.com/video-clips/uu5qz4/key-and-peele-dueling-hats</COMEDYCENTRAL></r>',
//				[],
//				function ($configurator)
//				{
//					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
//					$configurator->MediaEmbed->add('comedycentral');
//				}
//			],
			[
				'http://www.dumpert.nl/mediabase/6622577/4652b140/r_mi_gaillard_doet_halloween_prank.html',
				'<r><DUMPERT id="6622577/4652b140" url="http://www.dumpert.nl/mediabase/6622577/4652b140/r_mi_gaillard_doet_halloween_prank.html">http://www.dumpert.nl/mediabase/6622577/4652b140/r_mi_gaillard_doet_halloween_prank.html</DUMPERT></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('dumpert');
				}
			],
			[
				'http://8tracks.com/lovinq/headphones-in-world-out',
				'<r><EIGHTTRACKS id="4982023" url="http://8tracks.com/lovinq/headphones-in-world-out">http://8tracks.com/lovinq/headphones-in-world-out</EIGHTTRACKS></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('eighttracks');
				}
			],
			[
				'http://fora.tv/2009/07/30/Marijuana_Economics',
				'<r><FORATV id="9677" url="http://fora.tv/2009/07/30/Marijuana_Economics">http://fora.tv/2009/07/30/Marijuana_Economics</FORATV></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('foratv');
				}
			],
//			[
//				'http://www.gametrailers.com/videos/view/pop-fiction/102300-Metal-Gear-Solid-3-Still-in-a-Dream',
//				'<r><GAMETRAILERS id="2954127" url="http://www.gametrailers.com/videos/view/pop-fiction/102300-Metal-Gear-Solid-3-Still-in-a-Dream">http://www.gametrailers.com/videos/view/pop-fiction/102300-Metal-Gear-Solid-3-Still-in-a-Dream</GAMETRAILERS></r>',
//				[],
//				function ($configurator)
//				{
//					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
//					$configurator->MediaEmbed->add('gametrailers');
//				}
//			],
			[
				'http://gty.im/3232182',
				'(<r><GETTY et="[-\\w]{22}" height="399" id="3232182" sig="[-\\w]{43}=" url="http://gty.im/3232182" width="594">http://gty.im/3232182</GETTY></r>)',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('getty');
				},
				null,
				'assertRegexp'
			],
			[
				'http://www.gettyimages.com/detail/3232182',
				'(<r><GETTY et="[-\\w]{22}" height="399" id="3232182" sig="[-\\w]{43}=" url="http://www.gettyimages.com/detail/3232182" width="594">http://www.gettyimages.com/detail/3232182</GETTY></r>)',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('getty');
				},
				null,
				'assertRegexp'
			],
			[
				'http://www.gettyimages.com/detail/news-photo/the-beatles-travel-by-coach-to-the-west-country-for-some-news-photo/3232182',
				'(<r><GETTY et="[-\\w]{22}" height="399" id="3232182" sig="[-\\w]{43}=" url="http://www.gettyimages.com/detail/news-photo/the-beatles-travel-by-coach-to-the-west-country-for-some-news-photo/3232182" width="594">http://www.gettyimages.com/detail/news-photo/the-beatles-travel-by-coach-to-the-west-country-for-some-news-photo/3232182</GETTY></r>)',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('getty');
				},
				null,
				'assertRegexp'
			],
			[
				"http://www.gettyimages.co.jp/detail/%E3%83%8B%E3%83%A5%E3%83%BC%E3%82%B9%E5%86%99%E7%9C%9F/cher-lloyd-promotes-the-new-cd-sorry-im-late-at-nbc-experience-%E3%83%8B%E3%83%A5%E3%83%BC%E3%82%B9%E5%86%99%E7%9C%9F/494028667",
				'(<r><GETTY et="[-\\w]{22}" height="594" id="494028667" sig="[-\\w]{43}=" url="http://www.gettyimages.co.jp/detail/%E3%83%8B%E3%83%A5%E3%83%BC%E3%82%B9%E5%86%99%E7%9C%9F/cher-lloyd-promotes-the-new-cd-sorry-im-late-at-nbc-experience-%E3%83%8B%E3%83%A5%E3%83%BC%E3%82%B9%E5%86%99%E7%9C%9F/494028667" width="396">http://www.gettyimages.co.jp/detail/%E3%83%8B%E3%83%A5%E3%83%BC%E3%82%B9%E5%86%99%E7%9C%9F/cher-lloyd-promotes-the-new-cd-sorry-im-late-at-nbc-experience-%E3%83%8B%E3%83%A5%E3%83%BC%E3%82%B9%E5%86%99%E7%9C%9F/494028667</GETTY></r>)',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('getty');
				},
				null,
				'assertRegexp'
			],
			[
				"http://www.gettyimages.co.jp/detail/ニュース写真/cher-lloyd-promotes-the-new-cd-sorry-im-late-at-nbc-experience-ニュース写真/494028667",
				'(<r><GETTY et="[-\\w]{22}" height="594" id="494028667" sig="[-\\w]{43}=" url="http://www.gettyimages.co.jp/detail/%E3%83%8B%E3%83%A5%E3%83%BC%E3%82%B9%E5%86%99%E7%9C%9F/cher-lloyd-promotes-the-new-cd-sorry-im-late-at-nbc-experience-%E3%83%8B%E3%83%A5%E3%83%BC%E3%82%B9%E5%86%99%E7%9C%9F/494028667" width="396">http://www.gettyimages.co.jp/detail/ニュース写真/cher-lloyd-promotes-the-new-cd-sorry-im-late-at-nbc-experience-ニュース写真/494028667</GETTY></r>)',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('getty');
				},
				null,
				'assertRegexp'
			],
			[
				'http://www.gettyimages.com.au/event/celebrities-at-french-open-2016-day-seven-642247671#athlete-mariejose-perec-and-boxer-jeanmarc-mormeck-attend-the-france-picture-id534861206',
				'(<r><GETTY et="[-\\w]{22}" height="594" id="534861206" sig="[-\\w]{43}=" url="http://www.gettyimages.com.au/event/celebrities-at-french-open-2016-day-seven-642247671#athlete-mariejose-perec-and-boxer-jeanmarc-mormeck-attend-the-france-picture-id534861206" width="396">http://www.gettyimages.com.au/event/celebrities-at-french-open-2016-day-seven-642247671#athlete-mariejose-perec-and-boxer-jeanmarc-mormeck-attend-the-france-picture-id534861206</GETTY></r>)',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('getty');
				},
				null,
				'assertRegexp'
			],
			[
				'http://gfycat.com/SereneIllfatedCapybara',
				'<r><GFYCAT height="338" id="SereneIllfatedCapybara" url="http://gfycat.com/SereneIllfatedCapybara" width="600">http://gfycat.com/SereneIllfatedCapybara</GFYCAT></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('gfycat');
				}
			],
			[
				'https://gfycat.com/gifs/detail/LoathsomeHarmfulJenny',
				'<r><GFYCAT height="534" id="LoathsomeHarmfulJenny" url="https://gfycat.com/gifs/detail/LoathsomeHarmfulJenny" width="950">https://gfycat.com/gifs/detail/LoathsomeHarmfulJenny</GFYCAT></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('gfycat');
				}
			],
			[
				'http://gfycat.com/Test',
				'<r><GFYCAT height="360" id="Test" url="http://gfycat.com/Test" width="640">http://gfycat.com/Test</GFYCAT></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('gfycat');

					// Save an empty cache file that corresponds to this URL
					file_put_contents($configurator->registeredVars['cacheDir'] . '/http.3872667632.gz', gzencode(''));
				}
			],
			[
				'http://giant.gfycat.com/SereneIllfatedCapybara.gif',
				'<r><GFYCAT height="338" id="SereneIllfatedCapybara" url="http://giant.gfycat.com/SereneIllfatedCapybara.gif" width="600">http://giant.gfycat.com/SereneIllfatedCapybara.gif</GFYCAT></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('gfycat');
				}
			],
			[
				'https://j.gifs.com/Y6YZoO.gif',
				'<r><GIFS height="200" id="Y6YZoO" url="https://j.gifs.com/Y6YZoO.gif" width="200">https://j.gifs.com/Y6YZoO.gif</GIFS></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('gifs');
				}
			],
			[
				'http://www.hudl.com/v/CVmja',
				'<r><HUDL athlete="2122944" highlight="5721c090dfe23b2d68a2283b" url="http://www.hudl.com/v/CVmja">http://www.hudl.com/v/CVmja</HUDL></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('hudl');
				}
			],
//			[
//				'http://college.healthguru.com/video/handling-heartache',
//				'<r><HEALTHGURU id="ZX" url="http://college.healthguru.com/video/handling-heartache">http://college.healthguru.com/video/handling-heartache</HEALTHGURU></r>',
//				[],
//				function ($configurator)
//				{
//					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
//					$configurator->MediaEmbed->add('healthguru');
//				}
//			],
//			[
//				'http://college.healthguru.com/content/video/watch/100502/handling-heartache',
//				'<r><HEALTHGURU id="RX" url="http://college.healthguru.com/content/video/watch/100502/handling-heartache">http://college.healthguru.com/content/video/watch/100502/handling-heartache</HEALTHGURU></r>',
//				[],
//				function ($configurator)
//				{
//					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
//					$configurator->MediaEmbed->add('healthguru');
//				}
//			],
			[
				'http://www.hulu.com/watch/484180',
				'<r><HULU id="zPFCgxncn97IFkqEnZ-kRA" url="http://www.hulu.com/watch/484180">http://www.hulu.com/watch/484180</HULU></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('hulu');
				}
			],
			[
				'http://imgur.com/gallery/9UGCL',
				'<r><IMGUR id="9UGCL" type="album" url="http://imgur.com/gallery/9UGCL">http://imgur.com/gallery/9UGCL</IMGUR></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('imgur');
				}
			],
			[
				'http://imgur.com/gallery/49H5yU8',
				'<r><IMGUR id="49H5yU8" url="http://imgur.com/gallery/49H5yU8">http://imgur.com/gallery/49H5yU8</IMGUR></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('imgur');
				}
			],
			[
				'https://imgur.com/t/current_events/0I30l',
				'<r><IMGUR id="0I30l" type="album" url="https://imgur.com/t/current_events/0I30l">https://imgur.com/t/current_events/0I30l</IMGUR></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('imgur');
				}
			],
			[
				'https://m.imgur.com/t/funny/PZWhzCQ',
				'<r><IMGUR id="PZWhzCQ" url="https://m.imgur.com/t/funny/PZWhzCQ">https://m.imgur.com/t/funny/PZWhzCQ</IMGUR></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('imgur');
				}
			],
			[
				'https://archive.org/details/BillGate99',
				'<r><INTERNETARCHIVE height="240" id="BillGate99" url="https://archive.org/details/BillGate99" width="320">https://archive.org/details/BillGate99</INTERNETARCHIVE></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('internetarchive');
				}
			],
			[
				'https://archive.org/details/DFTS2014-05-30',
				'<r><INTERNETARCHIVE height="50" id="DFTS2014-05-30&amp;playlist=1" url="https://archive.org/details/DFTS2014-05-30" width="300">https://archive.org/details/DFTS2014-05-30</INTERNETARCHIVE></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('internetarchive');
				}
			],
			[
				'http://video.khl.ru/events/233677',
				'(<r><KHL id="free_\\w+_hd/2_5297335363/\\w+/\\d+" url="http://video.khl.ru/events/233677">http://video.khl.ru/events/233677</KHL></r>)',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('khl');
				},
				null,
				'assertRegexp'
			],
			[
				'http://video.khl.ru/quotes/251237',
				'(<r><KHL id="free_\\w+_hd/q251237/\\w+/\\d+" url="http://video.khl.ru/quotes/251237">http://video.khl.ru/quotes/251237</KHL></r>)',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('khl');
				},
				null,
				'assertRegexp'
			],
			[
				'http://bunkerbuddies.libsyn.com/interstellar-w-brandie-posey',
				'<r><LIBSYN id="3521244" url="http://bunkerbuddies.libsyn.com/interstellar-w-brandie-posey">http://bunkerbuddies.libsyn.com/interstellar-w-brandie-posey</LIBSYN></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('libsyn');
				}
			],
//			[
//				'http://livestre.am/1aHRU',
//				'<r><LIVESTREAM channel="maps_cp" clip_id="pla_d1501f90-438c-401d-98ae-e96ab34a09ae" url="http://livestre.am/1aHRU">http://livestre.am/1aHRU</LIVESTREAM></r>',
//				[],
//				function ($configurator)
//				{
//					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
//					$configurator->MediaEmbed->add('livestream');
//				}
//			],
			[
				'https://livestream.com/internetsociety/wsis/videos/107058039',
				'<r><LIVESTREAM account_id="686369" event_id="4588746" url="https://livestream.com/internetsociety/wsis/videos/107058039" video_id="107058039">https://livestream.com/internetsociety/wsis/videos/107058039</LIVESTREAM></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('livestream');
				}
			],
//			[
//				'http://my.mail.ru/corp/auto/video/testdrive/34.html',
//				'<r><MAILRU id="corp/auto/testdrive/34" url="http://my.mail.ru/corp/auto/video/testdrive/34.html">http://my.mail.ru/corp/auto/video/testdrive/34.html</MAILRU></r>',
//				[],
//				function ($configurator)
//				{
//					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
//					$configurator->MediaEmbed->add('mailru');
//				}
//			],
//			[
//				'http://dev.mrctv.org/videos/cnn-frets-about-tobacco-companies-color-coding-tricks',
//				'<r><MRCTV id="55537" url="http://dev.mrctv.org/videos/cnn-frets-about-tobacco-companies-color-coding-tricks">http://dev.mrctv.org/videos/cnn-frets-about-tobacco-companies-color-coding-tricks</MRCTV></r>',
//				[],
//				function ($configurator)
//				{
//					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
//					$configurator->MediaEmbed->add('mrctv');
//				}
//			],
			[
				'http://www.msnbc.com/ronan-farrow-daily/watch/thats-no-moon--300512323725',
				'<r><MSNBC id="n_farrow_moon_140709_257794" url="http://www.msnbc.com/ronan-farrow-daily/watch/thats-no-moon--300512323725">http://www.msnbc.com/ronan-farrow-daily/watch/thats-no-moon--300512323725</MSNBC></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('msnbc');
				}
			],
			[
				'http://on.msnbc.com/1qkH62o',
				'<r><MSNBC id="n_farrow_moon_140709_257794" url="http://on.msnbc.com/1qkH62o">http://on.msnbc.com/1qkH62o</MSNBC></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('msnbc');
				}
			],
			[
				'http://video.nationalgeographic.com/tv/changing-earth',
				'<r><NATGEOVIDEO id="ngc-4MlzV_K8XoTPdXPLx2NOWq2IH410IzpO" url="http://video.nationalgeographic.com/tv/changing-earth">http://video.nationalgeographic.com/tv/changing-earth</NATGEOVIDEO></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('natgeochannel');
					$configurator->MediaEmbed->add('natgeovideo');
				}
			],
			[
				'http://video.nationalgeographic.com/video/weirdest-superb-lyrebird',
				'<r><NATGEOVIDEO id="df825c71-a912-476b-be6a-a3fbffed1ae4" url="http://video.nationalgeographic.com/video/weirdest-superb-lyrebird">http://video.nationalgeographic.com/video/weirdest-superb-lyrebird</NATGEOVIDEO></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('natgeochannel');
					$configurator->MediaEmbed->add('natgeovideo');
				}
			],
			[
				'http://www.nbcsports.com/video/countdown-rio-olympics-what-makes-perfect-performance',
				'<r><NBCSPORTS id="fTQA2MMyx9YO" url="http://www.nbcsports.com/video/countdown-rio-olympics-what-makes-perfect-performance">http://www.nbcsports.com/video/countdown-rio-olympics-what-makes-perfect-performance</NBCSPORTS></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('nbcsports');
				}
			],
			[
				'http://www.npr.org/blogs/goatsandsoda/2015/02/11/385396431/the-50-most-effective-ways-to-transform-the-developing-world',
				'<r><NPR i="385396431" m="385396432" url="http://www.npr.org/blogs/goatsandsoda/2015/02/11/385396431/the-50-most-effective-ways-to-transform-the-developing-world">http://www.npr.org/blogs/goatsandsoda/2015/02/11/385396431/the-50-most-effective-ways-to-transform-the-developing-world</NPR></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('npr');
				}
			],
			[
				'http://n.pr/1Qky1m5',
				'<r><NPR i="411271189" m="411271193" url="http://n.pr/1Qky1m5">http://n.pr/1Qky1m5</NPR></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('npr');
				}
			],
			[
				'http://www.nytimes.com/movies/movie/131154/Crooklyn/trailers',
				'<r><NYTIMES id="100000003313949" url="http://www.nytimes.com/movies/movie/131154/Crooklyn/trailers">http://www.nytimes.com/movies/movie/131154/Crooklyn/trailers</NYTIMES></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('nytimes');
				}
			],
			[
				'http://movies.nytimes.com/movie/131154/Crooklyn/trailers',
				'<r><NYTIMES id="100000003313949" url="http://movies.nytimes.com/movie/131154/Crooklyn/trailers">http://movies.nytimes.com/movie/131154/Crooklyn/trailers</NYTIMES></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('nytimes');
				}
			],
			[
				'http://plays.tv/s/Kt4onQhyyVyz',
				'<r><PLAYSTV id="565683db95f139f47e" url="http://plays.tv/s/Kt4onQhyyVyz">http://plays.tv/s/Kt4onQhyyVyz</PLAYSTV></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('playstv');
				}
			],
			[
				'https://judodaveroman.podbean.com/e/judo-chop-suey-ep-20-freestyle-judo-founder-steve-scott/',
				'<r><PODBEAN id="gupid-6a18d0" url="https://judodaveroman.podbean.com/e/judo-chop-suey-ep-20-freestyle-judo-founder-steve-scott/">https://judodaveroman.podbean.com/e/judo-chop-suey-ep-20-freestyle-judo-founder-steve-scott/</PODBEAN></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('podbean');
				}
			],
			[
				'http://www.podbean.com/media/share/dir-vf532-2dd8497',
				'<r><PODBEAN id="vf532-2dd8497-dir" url="http://www.podbean.com/media/share/dir-vf532-2dd8497">http://www.podbean.com/media/share/dir-vf532-2dd8497</PODBEAN></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('podbean');
				}
			],
			[
				'http://rutube.ru/video/b920dc58f1397f1761a226baae4d2f3b/',
				'<r><RUTUBE id="6613980" url="http://rutube.ru/video/b920dc58f1397f1761a226baae4d2f3b/">http://rutube.ru/video/b920dc58f1397f1761a226baae4d2f3b/</RUTUBE></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('rutube');
				}
			],
			[
				'http://www.slideshare.net/Slideshare/10-million-uploads-our-favorites',
				'<r><SLIDESHARE id="21112125" url="http://www.slideshare.net/Slideshare/10-million-uploads-our-favorites">http://www.slideshare.net/Slideshare/10-million-uploads-our-favorites</SLIDESHARE></r>',
				[],
				function ($configurator)
				{
					if (isset($_SERVER['TRAVIS']))
					{
						$this->markTestSkipped('SlideShare blocks requests from Travis containers');
					}
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('slideshare');
				}
			],
//			[
//				'https://soundcloud.com/topdawgent/i-1/s-GT9Cd',
//				'<r><SOUNDCLOUD id="topdawgent/i-1/s-GT9Cd" secret_token="s-GT9Cd" track_id="168988860" url="https://soundcloud.com/topdawgent/i-1/s-GT9Cd">https://soundcloud.com/topdawgent/i-1/s-GT9Cd</SOUNDCLOUD></r>',
//				[],
//				function ($configurator)
//				{
//					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
//					$configurator->MediaEmbed->add('soundcloud');
//				}
//			],
			[
				'https://soundcloud.com/andrewbird/three-white-horses',
				'<r><SOUNDCLOUD id="andrewbird/three-white-horses" track_id="59509713" url="https://soundcloud.com/andrewbird/three-white-horses">https://soundcloud.com/andrewbird/three-white-horses</SOUNDCLOUD></r>',
				[],
				function ($configurator)
				{
					if (isset($_SERVER['TRAVIS']))
					{
						$this->markTestSkipped('SoundCloud does not like requests from Travis containers');
					}
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('soundcloud');
				}
			],
			[
				'Https://soundcloud.com/andrewbird/three-white-horses',
				'<r><SOUNDCLOUD id="andrewbird/three-white-horses" track_id="59509713" url="https://soundcloud.com/andrewbird/three-white-horses">Https://soundcloud.com/andrewbird/three-white-horses</SOUNDCLOUD></r>',
				[],
				function ($configurator)
				{
					if (isset($_SERVER['TRAVIS']))
					{
						$this->markTestSkipped('SoundCloud does not like requests from Travis containers');
					}
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('soundcloud');
				}
			],
			[
				'http://www.sportsnet.ca/videos/shows/tim-and-sid-video/',
				'(<r><SPORTSNET id="\\d+001" url="http://www.sportsnet.ca/videos/shows/tim-and-sid-video/">http://www.sportsnet.ca/videos/shows/tim-and-sid-video/</SPORTSNET></r>)',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('sportsnet');
				},
				null,
				'assertRegexp'
			],
//			[
//				'http://www.stitcher.com/podcast/twit/tech-news-today/e/twitter-shares-fall-18-percent-after-earnings-leak-on-twitter-37808629',
//				'<r><STITCHER eid="37808629" fid="12645" url="http://www.stitcher.com/podcast/twit/tech-news-today/e/twitter-shares-fall-18-percent-after-earnings-leak-on-twitter-37808629">http://www.stitcher.com/podcast/twit/tech-news-today/e/twitter-shares-fall-18-percent-after-earnings-leak-on-twitter-37808629</STITCHER></r>',
//				[],
//				function ($configurator)
//				{
//					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
//					$configurator->MediaEmbed->add('stitcher');
//				}
//			],
			[
				'http://teamcoco.com/video/serious-jibber-jabber-a-scott-berg-full-episode',
				'<r><TEAMCOCO id="73784" url="http://teamcoco.com/video/serious-jibber-jabber-a-scott-berg-full-episode">http://teamcoco.com/video/serious-jibber-jabber-a-scott-berg-full-episode</TEAMCOCO></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('teamcoco');
				}
			],
			[
				'http://tinypic.com/m/jujsk3/4',
				'<r><TINYPIC id="1gg7xj" s="9" url="http://tinypic.com/m/jujsk3/4">http://tinypic.com/m/jujsk3/4</TINYPIC></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('tinypic');
				}
			],
//			[
//				'http://www.traileraddict.com/robocop-2013/tv-spot-meet-the-future-ii',
//				'<r><TRAILERADDICT id="85253" url="http://www.traileraddict.com/robocop-2013/tv-spot-meet-the-future-ii">http://www.traileraddict.com/robocop-2013/tv-spot-meet-the-future-ii</TRAILERADDICT></r>',
//				[],
//				function ($configurator)
//				{
//					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
//					$configurator->MediaEmbed->add('traileraddict');
//				}
//			],
			[
				'http://mrbenvey.tumblr.com/post/104191225637',
				'<r><TUMBLR did="5f3b4bc6718317df9c2b1e77c20839ab94f949cd" id="104191225637" key="uFhWDPKj-bGU0ZlDAnUyxg" name="mrbenvey" url="http://mrbenvey.tumblr.com/post/104191225637">http://mrbenvey.tumblr.com/post/104191225637</TUMBLR></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('tumblr');
				}
			],
			[
				'http://www.ustream.tv/channel/ps4-ustream-gameplay',
				'<r><USTREAM cid="16234409" url="http://www.ustream.tv/channel/ps4-ustream-gameplay">http://www.ustream.tv/channel/ps4-ustream-gameplay</USTREAM></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('ustream');
				}
			],
			[
				'https://vid.me/rxMHW',
				'<r><VIDME id="rxMHW" url="https://vid.me/rxMHW">https://vid.me/rxMHW</VIDME></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('vidme');
				}
			],
			[
				'https://vid.me/Sentenza_Gaming',
				'<t>https://vid.me/Sentenza_Gaming</t>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('vidme');
				}
			],
			[
				'https://vid.me/6AZf1',
				'<r><VIDME id="6AZf1" url="https://vid.me/6AZf1">https://vid.me/6AZf1</VIDME></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('vidme');
				}
			],
			[
				'http://vkontakte.ru/video-7016284_163645555',
				'<r><VK hash="eb5d7a5e6e1d8b71" oid="-7016284" url="http://vkontakte.ru/video-7016284_163645555" vid="163645555">http://vkontakte.ru/video-7016284_163645555</VK></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('vk');
				}
			],
			[
				'http://vk.com/video226156999_168963041',
				'<r><VK hash="9050a9cce6465c9e" oid="226156999" url="http://vk.com/video226156999_168963041" vid="168963041">http://vk.com/video226156999_168963041</VK></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('vk');
				}
			],
			[
				'http://vk.com/newmusicvideos?z=video-13895667_161988074',
				'<r><VK hash="de860a8e4fbe45c9" oid="-13895667" url="http://vk.com/newmusicvideos?z=video-13895667_161988074" vid="161988074">http://vk.com/newmusicvideos?z=video-13895667_161988074</VK></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('vk');
				}
			],
			[
				'http://www.worldstarhiphop.com/videos/video.php?v=wshhZ8F22UtJ8sLHdja0',
				'<r><WSHH id="63133" url="http://www.worldstarhiphop.com/videos/video.php?v=wshhZ8F22UtJ8sLHdja0">http://www.worldstarhiphop.com/videos/video.php?v=wshhZ8F22UtJ8sLHdja0</WSHH></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('wshh');
				}
			],
			[
				'http://m.worldstarhiphop.com/video.php?v=wshh2SXFFe7W14DqQx61',
				'<r><WSHH id="63175" url="http://m.worldstarhiphop.com/video.php?v=wshh2SXFFe7W14DqQx61">http://m.worldstarhiphop.com/video.php?v=wshh2SXFFe7W14DqQx61</WSHH></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('wshh');
				}
			],
			[
				'http://m.worldstarhiphop.com/apple/video.php?v=wshh9yky3fx1Sj96E2mo',
				'<r><WSHH id="71468" url="http://m.worldstarhiphop.com/apple/video.php?v=wshh9yky3fx1Sj96E2mo">http://m.worldstarhiphop.com/apple/video.php?v=wshh9yky3fx1Sj96E2mo</WSHH></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('wshh');
				}
			],
			[
				'http://on.wsj.com/1MJvx06',
				'<r><WSJ id="9E476D54-6A60-4F3F-ABC1-411014552DE6" url="http://on.wsj.com/1MJvx06">http://on.wsj.com/1MJvx06</WSJ></r>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('wsj');
				}
			],
//			[
//				'https://www.youtube.com/shared?ci=_OouqtitfX4',
//				'<r><YOUTUBE id="qvW2nxnj9Tw" url="https://www.youtube.com/shared?ci=_OouqtitfX4">https://www.youtube.com/shared?ci=_OouqtitfX4</YOUTUBE></r>',
//				[],
//				function ($configurator)
//				{
//					// Skip during cache preload
//					if (isset($_SERVER['TRAVIS']) && isset($_SERVER['CACHE_PRELOAD']))
//					{
//						$this->markTestSkipped();
//					}
//
//					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
//					$configurator->MediaEmbed->add('youtube');
//				}
//			],
		];
	}

	/**
	* @testdox Scraping+rendering tests
	* @dataProvider getScrapingRenderingTests
	* @group needs-network
	*/
	public function testScrapingRendering()
	{
		$this->runScrapingTest('testRendering', func_get_args());
	}

	public function getScrapingRenderingTests()
	{
		return [
			[
				'http://proleter.bandcamp.com/album/curses-from-past-times-ep',
				'<span data-s9e-mediaembed="bandcamp" style="display:inline-block;width:100%;max-width:400px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:100%"><iframe allowfullscreen="" scrolling="no" style="border:0;height:100%;left:0;position:absolute;width:100%" src="//bandcamp.com/EmbeddedPlayer/size=large/minimal=true/album=1122163921"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('bandcamp');
				}
			],
			[
				'http://proleter.bandcamp.com/track/muhammad-ali',
				'<span data-s9e-mediaembed="bandcamp" style="display:inline-block;width:100%;max-width:400px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:100%"><iframe allowfullscreen="" scrolling="no" style="border:0;height:100%;left:0;position:absolute;width:100%" src="//bandcamp.com/EmbeddedPlayer/size=large/minimal=true/album=1122163921/t=7"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('bandcamp');
				}
			],
			[
				'http://therunons.bandcamp.com/track/still-feel',
				'<span data-s9e-mediaembed="bandcamp" style="display:inline-block;width:100%;max-width:400px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:100%"><iframe allowfullscreen="" scrolling="no" style="border:0;height:100%;left:0;position:absolute;width:100%" src="//bandcamp.com/EmbeddedPlayer/size=large/minimal=true/track=2146686782"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('bandcamp');
				}
			],
			[
				'http://www.cbsnews.com/videos/is-carbonated-water-a-healthy-option/',
				'<span data-s9e-mediaembed="cbsnews" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:62.1875%;padding-bottom:calc(56.25% + 38px)"><object data="//www.cbsnews.com/common/video/cbsnews_player.swf" style="height:100%;left:0;position:absolute;width:100%" type="application/x-shockwave-flash" typemustmatch=""><param name="allowfullscreen" value="true"><param name="flashvars" value="pType=embed&amp;si=254&amp;pid=B2AtjLUWB4Vj"></object></span></span>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('cbsnews');
				}
			],
//			[
//				'http://www.comedycentral.com/video-clips/uu5qz4/key-and-peele-dueling-hats',
//				'<span data-s9e-mediaembed="comedycentral" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" src="//media.mtvnservices.com/embed/mgid:arc:video:comedycentral.com:bc275e2f-48e3-46d9-b095-0254381497ea" style="border:0;height:100%;left:0;position:absolute;width:100%"></iframe></span></span>',
//				[],
//				function ($configurator)
//				{
//					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
//					$configurator->MediaEmbed->add('comedycentral');
//				}
//			],
//			[
//				'http://www.gametrailers.com/videos/view/pop-fiction/102300-Metal-Gear-Solid-3-Still-in-a-Dream',
//				'<span data-s9e-mediaembed="gametrailers" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" style="border:0;height:100%;left:0;position:absolute;width:100%" src="//embed.gametrailers.com/embed/2954127?embed=1&amp;suppressBumper=1"></iframe></span></span>',
//				[],
//				function ($configurator)
//				{
//					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
//					$configurator->MediaEmbed->add('gametrailers');
//				}
//			],
			[
				'http://www.gettyimages.com/detail/3232182',
				'(<span data-s9e-mediaembed="getty" style="display:inline-block;width:100%;max-width:594px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:75\\.4208754208754%;padding-bottom:calc\\(67\\.1717171717172% \\+ 49px\\)"><iframe allowfullscreen="" scrolling="no" src="//embed\\.gettyimages\\.com/embed/3232182\\?et=[-\\w]{22}&amp;sig=[-\\w]{43}=" style="border:0;height:100%;left:0;position:absolute;width:100%"></iframe></span></span>)',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('getty');
				},
				'assertRegexp'
			],
			[
				'http://gfycat.com/SereneIllfatedCapybara',
				'<span data-s9e-mediaembed="gfycat" style="display:inline-block;width:100%;max-width:600px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.3333333333333%"><iframe allowfullscreen="" scrolling="no" src="//gfycat.com/iframe/SereneIllfatedCapybara" style="border:0;height:100%;left:0;position:absolute;width:100%"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('gfycat');
				}
			],
			[
				'https://imgur.com/a/9UGCL',
				'<iframe data-s9e-mediaembed="imgur" allowfullscreen="" onload="var b=Math.random();window.addEventListener(\'message\',function(a){a.data.id==b&amp;&amp;(style.height=a.data.height+\'px\',style.width=a.data.width+\'px\')});contentWindow.postMessage(\'s9e:\'+b,\'https://s9e.github.io\')" scrolling="no" style="border:0;height:450px;max-width:100%;width:568px" src="https://s9e.github.io/iframe/imgur.min.html#a/9UGCL"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('imgur');
				}
			],
			[
				'https://imgur.com/t/current_events/0I30l',
				'<iframe data-s9e-mediaembed="imgur" allowfullscreen="" onload="var b=Math.random();window.addEventListener(\'message\',function(a){a.data.id==b&amp;&amp;(style.height=a.data.height+\'px\',style.width=a.data.width+\'px\')});contentWindow.postMessage(\'s9e:\'+b,\'https://s9e.github.io\')" scrolling="no" style="border:0;height:450px;max-width:100%;width:568px" src="https://s9e.github.io/iframe/imgur.min.html#a/0I30l"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('imgur');
				}
			],
			[
				'https://soundcloud.com/andrewbird/three-white-horses',
				'<iframe data-s9e-mediaembed="soundcloud" allowfullscreen="" scrolling="no" src="https://w.soundcloud.com/player/?url=https%3A//api.soundcloud.com/tracks/59509713&amp;secret_token=" style="border:0;height:166px;max-width:900px;width:100%"></iframe>',
				[],
				function ($configurator)
				{
					if (isset($_SERVER['TRAVIS']))
					{
						$this->markTestSkipped('SoundCloud does not like requests from Travis containers');
					}
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('soundcloud');
				}
			],
			[
				'http://www.ustream.tv/channel/ps4-ustream-gameplay',
				'<span data-s9e-mediaembed="ustream" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" src="//www.ustream.tv/embed/16234409?html5ui" style="border:0;height:100%;left:0;position:absolute;width:100%"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->registeredVars['cacheDir'] = __DIR__ . '/../../.cache';
					$configurator->MediaEmbed->add('ustream');
				}
			],
		];
	}

	public function getParsingTests()
	{
		return [
			[
				'http://abcnews.go.com/US/video/missing-malaysian-flight-words-revealed-hunt-continues-hundreds-22880799',
				'<r><ABCNEWS id="22880799" url="http://abcnews.go.com/US/video/missing-malaysian-flight-words-revealed-hunt-continues-hundreds-22880799">http://abcnews.go.com/US/video/missing-malaysian-flight-words-revealed-hunt-continues-hundreds-22880799</ABCNEWS></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('abcnews');
				}
			],
			[
				'http://abcnews.go.com/Politics/video/special-live-1-14476486',
				'<r><ABCNEWS id="14476486" url="http://abcnews.go.com/Politics/video/special-live-1-14476486">http://abcnews.go.com/Politics/video/special-live-1-14476486</ABCNEWS></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('abcnews');
				}
			],
			[
				'http://abcnews.go.com/video/embed?id=45798660',
				'<r><ABCNEWS id="45798660" url="http://abcnews.go.com/video/embed?id=45798660">http://abcnews.go.com/video/embed?id=45798660</ABCNEWS></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('abcnews');
				}
			],
			[
				'http://www.amazon.ca/gp/product/B00GQT1LNO/',
				'<r><AMAZON id="B00GQT1LNO" tld="ca" url="http://www.amazon.ca/gp/product/B00GQT1LNO/">http://www.amazon.ca/gp/product/B00GQT1LNO/</AMAZON></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('amazon');
				}
			],
			[
				'http://www.amazon.co.jp/gp/product/B003AKZ6I8/',
				'<r><AMAZON id="B003AKZ6I8" tld="jp" url="http://www.amazon.co.jp/gp/product/B003AKZ6I8/">http://www.amazon.co.jp/gp/product/B003AKZ6I8/</AMAZON></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('amazon');
				}
			],
			[
				'http://www.amazon.co.uk/gp/product/B00BET0NR6/',
				'<r><AMAZON id="B00BET0NR6" tld="uk" url="http://www.amazon.co.uk/gp/product/B00BET0NR6/">http://www.amazon.co.uk/gp/product/B00BET0NR6/</AMAZON></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('amazon');
				}
			],
			[
				'http://www.amazon.com/dp/B002MUC0ZY',
				'<r><AMAZON id="B002MUC0ZY" url="http://www.amazon.com/dp/B002MUC0ZY">http://www.amazon.com/dp/B002MUC0ZY</AMAZON></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('amazon');
				}
			],
			[
				'http://www.amazon.com/The-BeerBelly-200-001-80-Ounce-Belly/dp/B001RB2CXY/',
				'<r><AMAZON id="B001RB2CXY" url="http://www.amazon.com/The-BeerBelly-200-001-80-Ounce-Belly/dp/B001RB2CXY/">http://www.amazon.com/The-BeerBelly-200-001-80-Ounce-Belly/dp/B001RB2CXY/</AMAZON></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('amazon');
				}
			],
			[
				'http://www.amazon.com/gp/product/B0094H8H7I',
				'<r><AMAZON id="B0094H8H7I" url="http://www.amazon.com/gp/product/B0094H8H7I">http://www.amazon.com/gp/product/B0094H8H7I</AMAZON></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('amazon');
				}
			],
			[
				'http://www.amazon.de/Netgear-WN3100RP-100PES-Repeater-integrierte-Steckdose/dp/B00ET2LTE6/',
				'<r><AMAZON id="B00ET2LTE6" tld="de" url="http://www.amazon.de/Netgear-WN3100RP-100PES-Repeater-integrierte-Steckdose/dp/B00ET2LTE6/">http://www.amazon.de/Netgear-WN3100RP-100PES-Repeater-integrierte-Steckdose/dp/B00ET2LTE6/</AMAZON></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('amazon');
				}
			],
			[
				'http://www.amazon.es/Vans-OLD-SKOOL-BLACK-WHITE/dp/B000R3QPEA/',
				'<r><AMAZON id="B000R3QPEA" tld="es" url="http://www.amazon.es/Vans-OLD-SKOOL-BLACK-WHITE/dp/B000R3QPEA/">http://www.amazon.es/Vans-OLD-SKOOL-BLACK-WHITE/dp/B000R3QPEA/</AMAZON></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('amazon');
				}
			],
			[
				'http://www.amazon.fr/Vans-Authentic-Baskets-mixte-adulte/dp/B005NIKPAY/',
				'<r><AMAZON id="B005NIKPAY" tld="fr" url="http://www.amazon.fr/Vans-Authentic-Baskets-mixte-adulte/dp/B005NIKPAY/">http://www.amazon.fr/Vans-Authentic-Baskets-mixte-adulte/dp/B005NIKPAY/</AMAZON></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('amazon');
				}
			],
			[
				'http://www.amazon.in/Vans-Unisex-Authentic-Midnight-Sneakers/dp/B01I3LNWQG/',
				'<r><AMAZON id="B01I3LNWQG" tld="in" url="http://www.amazon.in/Vans-Unisex-Authentic-Midnight-Sneakers/dp/B01I3LNWQG/">http://www.amazon.in/Vans-Unisex-Authentic-Midnight-Sneakers/dp/B01I3LNWQG/</AMAZON></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('amazon');
				}
			],
			[
				'http://www.amazon.it/gp/product/B00JGOMIP6/',
				'<r><AMAZON id="B00JGOMIP6" tld="it" url="http://www.amazon.it/gp/product/B00JGOMIP6/">http://www.amazon.it/gp/product/B00JGOMIP6/</AMAZON></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('amazon');
				}
			],
			[
				'http://audioboo.fm/boos/2439994-deadline-day-update',
				'<r><AUDIOBOOM id="2439994" url="http://audioboo.fm/boos/2439994-deadline-day-update">http://audioboo.fm/boos/2439994-deadline-day-update</AUDIOBOOM></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('audioboom');
				}
			],
			[
				'https://audioboom.com/posts/2493448-robert-patrick',
				'<r><AUDIOBOOM id="2493448" url="https://audioboom.com/posts/2493448-robert-patrick">https://audioboom.com/posts/2493448-robert-patrick</AUDIOBOOM></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('audioboom');
				}
			],
			[
				'http://www.audiomack.com/song/random-2/buy-the-world-final-1',
				'<r><AUDIOMACK id="random-2/buy-the-world-final-1" mode="song" url="http://www.audiomack.com/song/random-2/buy-the-world-final-1">http://www.audiomack.com/song/random-2/buy-the-world-final-1</AUDIOMACK></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('audiomack');
				}
			],
			[
				'http://www.audiomack.com/album/hz-global/double-a-side-vol3',
				'<r><AUDIOMACK id="hz-global/double-a-side-vol3" mode="album" url="http://www.audiomack.com/album/hz-global/double-a-side-vol3">http://www.audiomack.com/album/hz-global/double-a-side-vol3</AUDIOMACK></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('audiomack');
				}
			],
			[
				'https://blab.im/thenewabc-what-difficulty-have-you-experienced-in-ethics',
				'<r><BLAB id="thenewabc-what-difficulty-have-you-experienced-in-ethics" url="https://blab.im/thenewabc-what-difficulty-have-you-experienced-in-ethics">https://blab.im/thenewabc-what-difficulty-have-you-experienced-in-ethics</BLAB></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('blab');
				}
			],
			[
				'https://blab.im/about',
				'<t>https://blab.im/about</t>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('blab');
				}
			],
			[
				'https://blab.im/search?q=key',
				'<t>https://blab.im/search?q=key</t>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('blab');
				}
			],
			[
				'http://www.break.com/video/video-game-playing-frog-wants-more-2278131',
				'<r><BREAK id="2278131" url="http://www.break.com/video/video-game-playing-frog-wants-more-2278131">http://www.break.com/video/video-game-playing-frog-wants-more-2278131</BREAK></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('break');
				}
			],
			[
				'http://www.cbsnews.com/video/watch/?id=50156501n',
				'<r><CBSNEWS id="50156501" url="http://www.cbsnews.com/video/watch/?id=50156501n">http://www.cbsnews.com/video/watch/?id=50156501n</CBSNEWS></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('cbsnews');
				}
			],
			[
				'http://video.cnbc.com/gallery/?video=3000269279',
				'<r><CNBC id="3000269279" url="http://video.cnbc.com/gallery/?video=3000269279">http://video.cnbc.com/gallery/?video=3000269279</CNBC></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('cnbc');
				}
			],
			[
				'http://edition.cnn.com/videos/tv/2015/06/09/airplane-yoga-rachel-crane-ts-orig.cnn',
				'<r><CNN id="tv/2015/06/09/airplane-yoga-rachel-crane-ts-orig.cnn" url="http://edition.cnn.com/videos/tv/2015/06/09/airplane-yoga-rachel-crane-ts-orig.cnn">http://edition.cnn.com/videos/tv/2015/06/09/airplane-yoga-rachel-crane-ts-orig.cnn</CNN></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('cnn');
				}
			],
			[
				'http://us.cnn.com/video/data/2.0/video/bestoftv/2013/10/23/vo-nr-prince-george-christening-arrival.cnn.html',
				'<r><CNN id="bestoftv/2013/10/23/vo-nr-prince-george-christening-arrival.cnn" url="http://us.cnn.com/video/data/2.0/video/bestoftv/2013/10/23/vo-nr-prince-george-christening-arrival.cnn.html">http://us.cnn.com/video/data/2.0/video/bestoftv/2013/10/23/vo-nr-prince-george-christening-arrival.cnn.html</CNN></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('cnn');
				}
			],
			[
				'http://us.cnn.com/video/data/2.0/video/bestoftv/2013/10/23/vo-nr-prince-george-christening-arrival.cnn.html',
				'<r><CNN id="bestoftv/2013/10/23/vo-nr-prince-george-christening-arrival.cnn" url="http://us.cnn.com/video/data/2.0/video/bestoftv/2013/10/23/vo-nr-prince-george-christening-arrival.cnn.html">http://us.cnn.com/video/data/2.0/video/bestoftv/2013/10/23/vo-nr-prince-george-christening-arrival.cnn.html</CNN></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('cnn');
					$configurator->MediaEmbed->add('cnnmoney');
				}
			],
			[
				'http://www.cnn.com/video/data/2.0/video/us/2014/09/01/lead-dnt-brown-property-seizures.cnn.html',
				'<r><CNN id="us/2014/09/01/lead-dnt-brown-property-seizures.cnn" url="http://www.cnn.com/video/data/2.0/video/us/2014/09/01/lead-dnt-brown-property-seizures.cnn.html">http://www.cnn.com/video/data/2.0/video/us/2014/09/01/lead-dnt-brown-property-seizures.cnn.html</CNN></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('cnn');
					$configurator->MediaEmbed->add('cnnmoney');
				}
			],
			[
				'http://money.cnn.com/video/technology/2014/05/20/t-twitch-vp-on-future.cnnmoney/',
				'<r><CNNMONEY id="technology/2014/05/20/t-twitch-vp-on-future.cnnmoney" url="http://money.cnn.com/video/technology/2014/05/20/t-twitch-vp-on-future.cnnmoney/">http://money.cnn.com/video/technology/2014/05/20/t-twitch-vp-on-future.cnnmoney/</CNNMONEY></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('cnnmoney');
				}
			],
			[
				'http://money.cnn.com/video/technology/2014/05/20/t-twitch-vp-on-future.cnnmoney/',
				'<r><CNNMONEY id="technology/2014/05/20/t-twitch-vp-on-future.cnnmoney" url="http://money.cnn.com/video/technology/2014/05/20/t-twitch-vp-on-future.cnnmoney/">http://money.cnn.com/video/technology/2014/05/20/t-twitch-vp-on-future.cnnmoney/</CNNMONEY></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('cnn');
					$configurator->MediaEmbed->add('cnnmoney');
				}
			],
			[
				'http://www.collegehumor.com/video/1181601/more-than-friends',
				'<r><COLLEGEHUMOR id="1181601" url="http://www.collegehumor.com/video/1181601/more-than-friends">http://www.collegehumor.com/video/1181601/more-than-friends</COLLEGEHUMOR></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('collegehumor');
				}
			],
			[
				'http://coub.com/view/6veusoty',
				'<r><COUB id="6veusoty" url="http://coub.com/view/6veusoty">http://coub.com/view/6veusoty</COUB></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('coub');
				}
			],
			[
				'http://www.dailymotion.com/video/x222z1',
				'<r><DAILYMOTION id="x222z1" url="http://www.dailymotion.com/video/x222z1">http://www.dailymotion.com/video/x222z1</DAILYMOTION></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('dailymotion');
				}
			],
			[
				'http://www.dailymotion.com/user/Dailymotion/2#video=x222z1',
				'<r><DAILYMOTION id="x222z1" url="http://www.dailymotion.com/user/Dailymotion/2#video=x222z1">http://www.dailymotion.com/user/Dailymotion/2#video=x222z1</DAILYMOTION></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('dailymotion');
				}
			],
			[
				'http://games.dailymotion.com/live/x15gjhi',
				'<r><DAILYMOTION id="x15gjhi" url="http://games.dailymotion.com/live/x15gjhi">http://games.dailymotion.com/live/x15gjhi</DAILYMOTION></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('dailymotion');
				}
			],
			[
				'http://www.dailymotion.com/related/2344952/video/x12w88_le-peril-jeune_fun',
				'<r><DAILYMOTION id="x12w88" url="http://www.dailymotion.com/related/2344952/video/x12w88_le-peril-jeune_fun">http://www.dailymotion.com/related/2344952/video/x12w88_le-peril-jeune_fun</DAILYMOTION></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('dailymotion');
				}
			],
			[
				'http://www.democracynow.org/2014/7/2/dn_at_almedalen_week_at_swedens',
				'<r><DEMOCRACYNOW id="2014/7/2/dn_at_almedalen_week_at_swedens" url="http://www.democracynow.org/2014/7/2/dn_at_almedalen_week_at_swedens">http://www.democracynow.org/2014/7/2/dn_at_almedalen_week_at_swedens</DEMOCRACYNOW></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('democracynow');
				}
			],
			[
				'http://www.democracynow.org/blog/2015/3/13/part_2_bruce_schneier_on_the',
				'<r><DEMOCRACYNOW id="blog/2015/3/13/part_2_bruce_schneier_on_the" url="http://www.democracynow.org/blog/2015/3/13/part_2_bruce_schneier_on_the">http://www.democracynow.org/blog/2015/3/13/part_2_bruce_schneier_on_the</DEMOCRACYNOW></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('democracynow');
				}
			],
			[
				'http://www.democracynow.org/shows/2006/2/20',
				'<r><DEMOCRACYNOW id="shows/2006/2/20" url="http://www.democracynow.org/shows/2006/2/20">http://www.democracynow.org/shows/2006/2/20</DEMOCRACYNOW></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('democracynow');
				}
			],
			[
				'http://8tracks.com/midna/2242699',
				'<r><EIGHTTRACKS id="2242699" url="http://8tracks.com/midna/2242699">http://8tracks.com/midna/2242699</EIGHTTRACKS></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('eighttracks');
				}
			],
			[
				'http://www.espn.com/video/clip?id=17474659',
				'<r><ESPN id="17474659" url="http://www.espn.com/video/clip?id=17474659">http://www.espn.com/video/clip?id=17474659</ESPN></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('espn');
				}
			],
			[
				'http://www.espn.com/video/clip/_/id/17474659/categoryid/2564308',
				'<r><ESPN id="17474659" url="http://www.espn.com/video/clip/_/id/17474659/categoryid/2564308">http://www.espn.com/video/clip/_/id/17474659/categoryid/2564308</ESPN></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('espn');
				}
			],
			[
				'http://www.espn.com/espnw/video/13887284/kyrgios-angry-code-violation-almost-hitting-ref',
				'<r><ESPN id="13887284" url="http://www.espn.com/espnw/video/13887284/kyrgios-angry-code-violation-almost-hitting-ref">http://www.espn.com/espnw/video/13887284/kyrgios-angry-code-violation-almost-hitting-ref</ESPN></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('espn');
				}
			],
			[
				'http://broadband.espn.go.com/video/clip?id=17481969',
				'<r><ESPN id="17481969" url="http://broadband.espn.go.com/video/clip?id=17481969">http://broadband.espn.go.com/video/clip?id=17481969</ESPN></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('espn');
				}
			],
			[
				'https://www.facebook.com/photo.php?v=10100658170103643&set=vb.20531316728&type=3&theater',
				'<r><FACEBOOK id="10100658170103643" url="https://www.facebook.com/photo.php?v=10100658170103643&amp;set=vb.20531316728&amp;type=3&amp;theater">https://www.facebook.com/photo.php?v=10100658170103643&amp;set=vb.20531316728&amp;type=3&amp;theater</FACEBOOK></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('facebook');
				}
			],
			[
				'https://www.facebook.com/video/video.php?v=10150451523596807',
				'<r><FACEBOOK id="10150451523596807" type="video" url="https://www.facebook.com/video/video.php?v=10150451523596807">https://www.facebook.com/video/video.php?v=10150451523596807</FACEBOOK></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('facebook');
				}
			],
			[
				'https://www.facebook.com/FacebookDevelopers/posts/10151471074398553',
				'<r><FACEBOOK id="10151471074398553" url="https://www.facebook.com/FacebookDevelopers/posts/10151471074398553">https://www.facebook.com/FacebookDevelopers/posts/10151471074398553</FACEBOOK></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('facebook');
				}
			],
			[
				'https://de-de.facebook.com/FacebookDevelopers/posts/10151471074398553',
				'<r><FACEBOOK id="10151471074398553" url="https://de-de.facebook.com/FacebookDevelopers/posts/10151471074398553">https://de-de.facebook.com/FacebookDevelopers/posts/10151471074398553</FACEBOOK></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('facebook');
				}
			],
			[
				'https://www.facebook.com/photo.php?fbid=10152476416772631',
				'<r><FACEBOOK id="10152476416772631" url="https://www.facebook.com/photo.php?fbid=10152476416772631">https://www.facebook.com/photo.php?fbid=10152476416772631</FACEBOOK></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('facebook');
				}
			],
			[
				'https://www.facebook.com/pages/Bourne-Ultimatum/105742379466221',
				'<t>https://www.facebook.com/pages/Bourne-Ultimatum/105742379466221</t>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('facebook');
				}
			],
			[
				'https://apps.facebook.com/concertsbybit/facebook/events/8362556/rsvp',
				'<t>https://apps.facebook.com/concertsbybit/facebook/events/8362556/rsvp</t>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('facebook');
				}
			],
			[
				'https://www.facebook.com/events/640436826054815/',
				'<r><FACEBOOK id="640436826054815" url="https://www.facebook.com/events/640436826054815/">https://www.facebook.com/events/640436826054815/</FACEBOOK></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('facebook');
				}
			],
			[
				'https://www.facebook.com/groups/257086497821359/',
				'<t>https://www.facebook.com/groups/257086497821359/</t>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('facebook');
				}
			],
			[
				'https://www.facebook.com/groups/257086497821359/permalink/262329290630413/',
				'<r><FACEBOOK id="262329290630413" url="https://www.facebook.com/groups/257086497821359/permalink/262329290630413/">https://www.facebook.com/groups/257086497821359/permalink/262329290630413/</FACEBOOK></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('facebook');
				}
			],
			[
				'https://www.facebook.com/permalink.php?story_fbid=10152253595081467&id=58617016466',
				'<r><FACEBOOK id="10152253595081467" url="https://www.facebook.com/permalink.php?story_fbid=10152253595081467&amp;id=58617016466">https://www.facebook.com/permalink.php?story_fbid=10152253595081467&amp;id=58617016466</FACEBOOK></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('facebook');
				}
			],
			[
				'https://www.facebook.com/events/787127511306384/permalink/849632838389184/',
				'<r><FACEBOOK id="849632838389184" url="https://www.facebook.com/events/787127511306384/permalink/849632838389184/">https://www.facebook.com/events/787127511306384/permalink/849632838389184/</FACEBOOK></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('facebook');
				}
			],
			[
				'https://web.facebook.com/VijayTelevision/videos/948642131881684/',
				'<r><FACEBOOK id="948642131881684" type="video" url="https://web.facebook.com/VijayTelevision/videos/948642131881684/">https://web.facebook.com/VijayTelevision/videos/948642131881684/</FACEBOOK></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('facebook');
				}
			],
			[
				'https://www.flickr.com/photos/erigion/15451038758/in/photostream/',
				'<r><FLICKR id="15451038758" url="https://www.flickr.com/photos/erigion/15451038758/in/photostream/">https://www.flickr.com/photos/erigion/15451038758/in/photostream/</FLICKR></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('flickr');
				}
			],
			[
				'http://video.foxnews.com/v/3592758613001/reddit-helps-fund-homemade-hot-sauce-venture/',
				'<r><FOXNEWS id="3592758613001" url="http://video.foxnews.com/v/3592758613001/reddit-helps-fund-homemade-hot-sauce-venture/">http://video.foxnews.com/v/3592758613001/reddit-helps-fund-homemade-hot-sauce-venture/</FOXNEWS></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('foxnews');
				}
			],
			[
				'http://www.funnyordie.com/videos/bf313bd8b4/murdock-with-keith-david',
				'<r><FUNNYORDIE id="bf313bd8b4" url="http://www.funnyordie.com/videos/bf313bd8b4/murdock-with-keith-david">http://www.funnyordie.com/videos/bf313bd8b4/murdock-with-keith-david</FUNNYORDIE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('funnyordie');
				}
			],
			[
				'http://www.gamespot.com/destiny/videos/destiny-the-moon-trailer-6415176/',
				'<r><GAMESPOT id="6415176" url="http://www.gamespot.com/destiny/videos/destiny-the-moon-trailer-6415176/">http://www.gamespot.com/destiny/videos/destiny-the-moon-trailer-6415176/</GAMESPOT></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('gamespot');
				}
			],
			[
				'http://www.gamespot.com/events/game-crib-tsm-snapdragon/gamecrib-extras-cooking-with-dan-dinh-6412922/',
				'<r><GAMESPOT id="6412922" url="http://www.gamespot.com/events/game-crib-tsm-snapdragon/gamecrib-extras-cooking-with-dan-dinh-6412922/">http://www.gamespot.com/events/game-crib-tsm-snapdragon/gamecrib-extras-cooking-with-dan-dinh-6412922/</GAMESPOT></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('gamespot');
				}
			],
			[
				'http://www.gamespot.com/videos/beat-the-pros-pax-prime-2013/2300-6414307/',
				'<r><GAMESPOT id="6414307" url="http://www.gamespot.com/videos/beat-the-pros-pax-prime-2013/2300-6414307/">http://www.gamespot.com/videos/beat-the-pros-pax-prime-2013/2300-6414307/</GAMESPOT></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('gamespot');
				}
			],
			[
				'https://gist.github.com/s9e/6806305',
				'<r><GIST id="s9e/6806305" url="https://gist.github.com/s9e/6806305">https://gist.github.com/s9e/6806305</GIST></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('gist');
				}
			],
			[
				'https://gist.github.com/6806305',
				'<r><GIST id="6806305" url="https://gist.github.com/6806305">https://gist.github.com/6806305</GIST></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('gist');
				}
			],
			[
				'https://gist.github.com/s9e/6806305/ad88d904b082c8211afa040162402015aacb8599',
				'<r><GIST id="s9e/6806305/ad88d904b082c8211afa040162402015aacb8599" url="https://gist.github.com/s9e/6806305/ad88d904b082c8211afa040162402015aacb8599">https://gist.github.com/s9e/6806305/ad88d904b082c8211afa040162402015aacb8599</GIST></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('gist');
				}
			],
			[
				'https://gist.github.com/s9e/0ee8433f5a9a779d08ef',
				'<r><GIST id="s9e/0ee8433f5a9a779d08ef" url="https://gist.github.com/s9e/0ee8433f5a9a779d08ef">https://gist.github.com/s9e/0ee8433f5a9a779d08ef</GIST></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('gist');
				}
			],
			[
				'http://globalnews.ca/video/1647385/mark-channels-his-70s-look/',
				'<r><GLOBALNEWS id="1647385" url="http://globalnews.ca/video/1647385/mark-channels-his-70s-look/">http://globalnews.ca/video/1647385/mark-channels-his-70s-look/</GLOBALNEWS></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('globalnews');
				}
			],
			[
				'http://www.gofundme.com/2p37ao',
				'<r><GOFUNDME id="2p37ao" url="http://www.gofundme.com/2p37ao">http://www.gofundme.com/2p37ao</GOFUNDME></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('gofundme');
				}
			],
			[
				'http://www.gofundme.com/2p37ao#',
				'<r><GOFUNDME id="2p37ao" url="http://www.gofundme.com/2p37ao#">http://www.gofundme.com/2p37ao#</GOFUNDME></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('gofundme');
				}
			],
			[
				'http://www.gofundme.com/2p37ao?pc=trend',
				'<r><GOFUNDME id="2p37ao" url="http://www.gofundme.com/2p37ao?pc=trend">http://www.gofundme.com/2p37ao?pc=trend</GOFUNDME></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('gofundme');
				}
			],
			[
				'http://www.gofundme.com/tour/',
				'<t>http://www.gofundme.com/tour/</t>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('gofundme');
				}
			],
			[
				'https://drive.google.com/file/d/0B_4NRUjxLBejNjVmeG5MUzA3Q3M/view?usp=sharing',
				'<r><GOOGLEDRIVE id="0B_4NRUjxLBejNjVmeG5MUzA3Q3M" url="https://drive.google.com/file/d/0B_4NRUjxLBejNjVmeG5MUzA3Q3M/view?usp=sharing">https://drive.google.com/file/d/0B_4NRUjxLBejNjVmeG5MUzA3Q3M/view?usp=sharing</GOOGLEDRIVE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('googledrive');
				}
			],
			[
				// https://github.com/s9e/phpbb-ext-mediaembed/issues/9
				'https://drive.google.com/open?id=1TAnofDHLM-Mreaju0l3--9SQAESEIpD9AA',
				'<r><GOOGLEDRIVE id="1TAnofDHLM-Mreaju0l3--9SQAESEIpD9AA" url="https://drive.google.com/open?id=1TAnofDHLM-Mreaju0l3--9SQAESEIpD9AA">https://drive.google.com/open?id=1TAnofDHLM-Mreaju0l3--9SQAESEIpD9AA</GOOGLEDRIVE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('googledrive');
				}
			],
			[
				'https://drive.google.com/a/monashores.net/file/d/0B-SjC2QWxqXRY2NfbGJ2QUcwTlU/view',
				'<r><GOOGLEDRIVE id="0B-SjC2QWxqXRY2NfbGJ2QUcwTlU" url="https://drive.google.com/a/monashores.net/file/d/0B-SjC2QWxqXRY2NfbGJ2QUcwTlU/view">https://drive.google.com/a/monashores.net/file/d/0B-SjC2QWxqXRY2NfbGJ2QUcwTlU/view</GOOGLEDRIVE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('googledrive');
				}
			],
			[
				'https://plus.google.com/110286587261352351537/posts/XMABm8rLvRW',
				'<r><GOOGLEPLUS oid="110286587261352351537" pid="XMABm8rLvRW" url="https://plus.google.com/110286587261352351537/posts/XMABm8rLvRW">https://plus.google.com/110286587261352351537/posts/XMABm8rLvRW</GOOGLEPLUS></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('googleplus');
				}
			],
			[
				'https://plus.google.com/+JacekMiłaszewski/posts/HJEFk3SX1sL',
				'<r><GOOGLEPLUS name="JacekMiłaszewski" pid="HJEFk3SX1sL" url="https://plus.google.com/+JacekMi%C5%82aszewski/posts/HJEFk3SX1sL">https://plus.google.com/+JacekMiłaszewski/posts/HJEFk3SX1sL</GOOGLEPLUS></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('googleplus');
				}
			],
			[
				'https://plus.google.com/+JacekMi%C5%82aszewski/posts/HJEFk3SX1sL',
				'<r><GOOGLEPLUS name="JacekMiłaszewski" pid="HJEFk3SX1sL" url="https://plus.google.com/+JacekMi%C5%82aszewski/posts/HJEFk3SX1sL">https://plus.google.com/+JacekMi%C5%82aszewski/posts/HJEFk3SX1sL</GOOGLEPLUS></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('googleplus');
				}
			],
			[
				'https://docs.google.com/spreadsheets/d/1e-WiRxaToQyKPkm1x8hRu6cN6K0aQFxExo7RnCymxGE',
				'<r><GOOGLESHEETS id="1e-WiRxaToQyKPkm1x8hRu6cN6K0aQFxExo7RnCymxGE" url="https://docs.google.com/spreadsheets/d/1e-WiRxaToQyKPkm1x8hRu6cN6K0aQFxExo7RnCymxGE">https://docs.google.com/spreadsheets/d/1e-WiRxaToQyKPkm1x8hRu6cN6K0aQFxExo7RnCymxGE</GOOGLESHEETS></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('googlesheets');
				}
			],
			[
				'https://docs.google.com/spreadsheet/ccc?key=0AnfAFqEAnlFvdG5IMDdnd0xZQUlxZkdxbzg5SGZJQlE&usp=sharing',
				'<r><GOOGLESHEETS id="0AnfAFqEAnlFvdG5IMDdnd0xZQUlxZkdxbzg5SGZJQlE" url="https://docs.google.com/spreadsheet/ccc?key=0AnfAFqEAnlFvdG5IMDdnd0xZQUlxZkdxbzg5SGZJQlE&amp;usp=sharing">https://docs.google.com/spreadsheet/ccc?key=0AnfAFqEAnlFvdG5IMDdnd0xZQUlxZkdxbzg5SGZJQlE&amp;usp=sharing</GOOGLESHEETS></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('googlesheets');
				}
			],
			[
				'https://docs.google.com/spreadsheet/ccc?key=0An1aCHqyU7FqdGtBUDc1S1NNSWhqY3NidndIa1JuQWc#gid=70',
				'<r><GOOGLESHEETS gid="70" id="0An1aCHqyU7FqdGtBUDc1S1NNSWhqY3NidndIa1JuQWc" url="https://docs.google.com/spreadsheet/ccc?key=0An1aCHqyU7FqdGtBUDc1S1NNSWhqY3NidndIa1JuQWc#gid=70">https://docs.google.com/spreadsheet/ccc?key=0An1aCHqyU7FqdGtBUDc1S1NNSWhqY3NidndIa1JuQWc#gid=70</GOOGLESHEETS></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('googlesheets');
				}
			],
			[
				'http://www.hudl.com/athlete/2067184/highlights/163744377',
				'<r><HUDL athlete="2067184" highlight="163744377" url="http://www.hudl.com/athlete/2067184/highlights/163744377">http://www.hudl.com/athlete/2067184/highlights/163744377</HUDL></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('hudl');
				}
			],
			[
				'http://www.hudl.com/video/3/323679/57719969842eb243e47883f8',
				'<r><HUDL athlete="323679" highlight="57719969842eb243e47883f8" url="http://www.hudl.com/video/3/323679/57719969842eb243e47883f8">http://www.hudl.com/video/3/323679/57719969842eb243e47883f8</HUDL></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('hudl');
				}
			],
			[
				'http://humortv.vara.nl/ca.344063.de-klusjesmannen-zijn-weer-van-de-partij.html',
				'<r><HUMORTVNL id="344063.de-klusjesmannen-zijn-weer-van-de-partij" url="http://humortv.vara.nl/ca.344063.de-klusjesmannen-zijn-weer-van-de-partij.html">http://humortv.vara.nl/ca.344063.de-klusjesmannen-zijn-weer-van-de-partij.html</HUMORTVNL></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('humortvnl');
				}
			],
			[
				'http://humortv.vara.nl/pa.346135.denzel-washington-bij-graham-norton.html',
				'<r><HUMORTVNL id="346135.denzel-washington-bij-graham-norton" url="http://humortv.vara.nl/pa.346135.denzel-washington-bij-graham-norton.html">http://humortv.vara.nl/pa.346135.denzel-washington-bij-graham-norton.html</HUMORTVNL></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('humortvnl');
				}
			],
			[
				'http://uk.ign.com/videos/2013/07/12/pokemon-x-version-pokemon-y-version-battle-trailer',
				'<r><IGN id="http://uk.ign.com/videos/2013/07/12/pokemon-x-version-pokemon-y-version-battle-trailer" url="http://uk.ign.com/videos/2013/07/12/pokemon-x-version-pokemon-y-version-battle-trailer">http://uk.ign.com/videos/2013/07/12/pokemon-x-version-pokemon-y-version-battle-trailer</IGN></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('ign');
				}
			],
			[
				'Http://uk.ign.com/videos/2013/07/12/pokemon-x-version-pokemon-y-version-battle-trailer',
				'<r><IGN id="Http://uk.ign.com/videos/2013/07/12/pokemon-x-version-pokemon-y-version-battle-trailer" url="http://uk.ign.com/videos/2013/07/12/pokemon-x-version-pokemon-y-version-battle-trailer">Http://uk.ign.com/videos/2013/07/12/pokemon-x-version-pokemon-y-version-battle-trailer</IGN></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('ign');
				}
			],
			[
				'http://www.imdb.com/video/epk/vi387296537/',
				'<r><IMDB id="387296537" url="http://www.imdb.com/video/epk/vi387296537/">http://www.imdb.com/video/epk/vi387296537/</IMDB></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('imdb');
				}
			],
			[
				'http://www.imdb.com/title/tt2294629/videoplayer/vi2482677785',
				'<r><IMDB id="2482677785" url="http://www.imdb.com/title/tt2294629/videoplayer/vi2482677785">http://www.imdb.com/title/tt2294629/videoplayer/vi2482677785</IMDB></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('imdb');
				}
			],
			[
				'http://i.imgur.com/AsQ0K3P.jpg',
				'<t>http://i.imgur.com/AsQ0K3P.jpg</t>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('imgur');
				}
			],
			[
				'http://imgur.com/r/animals',
				'<t>http://imgur.com/r/animals</t>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('imgur');
				}
			],
			[
				'http://imgur.com/user/foo',
				'<t>http://imgur.com/user/foo</t>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('imgur');
				}
			],
			[
				'https://imgur.com/a/9UGCL',
				'<r><IMGUR id="a/9UGCL" url="https://imgur.com/a/9UGCL">https://imgur.com/a/9UGCL</IMGUR></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('imgur');
				}
			],
			[
				'http://www.indiegogo.com/projects/513633',
				'<r><INDIEGOGO id="513633" url="http://www.indiegogo.com/projects/513633">http://www.indiegogo.com/projects/513633</INDIEGOGO></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('indiegogo');
				}
			],
			[
				'http://www.indiegogo.com/projects/gameheart-redesigned',
				'<r><INDIEGOGO id="gameheart-redesigned" url="http://www.indiegogo.com/projects/gameheart-redesigned">http://www.indiegogo.com/projects/gameheart-redesigned</INDIEGOGO></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('indiegogo');
				}
			],
			[
				'http://www.indiegogo.com/projects/5050-years-a-documentary',
				'<r><INDIEGOGO id="5050-years-a-documentary" url="http://www.indiegogo.com/projects/5050-years-a-documentary">http://www.indiegogo.com/projects/5050-years-a-documentary</INDIEGOGO></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('indiegogo');
				}
			],
			[
				'http://instagram.com/p/gbGaIXBQbn/',
				'<r><INSTAGRAM id="gbGaIXBQbn" url="http://instagram.com/p/gbGaIXBQbn/">http://instagram.com/p/gbGaIXBQbn/</INSTAGRAM></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('instagram');
				}
			],
			[
				'http://instagram.com/p/lx39ciHzD_/',
				'<r><INSTAGRAM id="lx39ciHzD_" url="http://instagram.com/p/lx39ciHzD_/">http://instagram.com/p/lx39ciHzD_/</INSTAGRAM></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('instagram');
				}
			],
			[
				'http://instagram.com/p/k28LE0Dte-/',
				'<r><INSTAGRAM id="k28LE0Dte-" url="http://instagram.com/p/k28LE0Dte-/">http://instagram.com/p/k28LE0Dte-/</INSTAGRAM></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('instagram');
				}
			],
			[
				'http://www.izlesene.com/video/lily-allen-url-badman/7600704',
				'<r><IZLESENE id="7600704" url="http://www.izlesene.com/video/lily-allen-url-badman/7600704">http://www.izlesene.com/video/lily-allen-url-badman/7600704</IZLESENE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('izlesene');
				}
			],
			[
				'http://content.jwplatform.com/players/X6tRZpKj-7Y21S9TB.html',
				'<r><JWPLATFORM id="X6tRZpKj-7Y21S9TB" url="http://content.jwplatform.com/players/X6tRZpKj-7Y21S9TB.html">http://content.jwplatform.com/players/X6tRZpKj-7Y21S9TB.html</JWPLATFORM></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('jwplatform');
				}
			],
			[
				'http://content.jwplatform.com/previews/8YYjnBKd-plsZnDJi',
				'<r><JWPLATFORM id="8YYjnBKd-plsZnDJi" url="http://content.jwplatform.com/previews/8YYjnBKd-plsZnDJi">http://content.jwplatform.com/previews/8YYjnBKd-plsZnDJi</JWPLATFORM></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('jwplatform');
				}
			],
			[
				'http://content.jwplatform.com/videos/5W1gTgdo-wevz73lD.mp4',
				'<r><JWPLATFORM id="5W1gTgdo-wevz73lD" url="http://content.jwplatform.com/videos/5W1gTgdo-wevz73lD.mp4">http://content.jwplatform.com/videos/5W1gTgdo-wevz73lD.mp4</JWPLATFORM></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('jwplatform');
				}
			],
			[
				'http://www.kickstarter.com/projects/1869987317/wish-i-was-here-1?ref=',
				'<r><KICKSTARTER id="1869987317/wish-i-was-here-1" url="http://www.kickstarter.com/projects/1869987317/wish-i-was-here-1?ref=">http://www.kickstarter.com/projects/1869987317/wish-i-was-here-1?ref=</KICKSTARTER></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('kickstarter');
				}
			],
			[
				'http://www.kickstarter.com/projects/1869987317/wish-i-was-here-1/widget/card.html',
				'<r><KICKSTARTER card="card" id="1869987317/wish-i-was-here-1" url="http://www.kickstarter.com/projects/1869987317/wish-i-was-here-1/widget/card.html">http://www.kickstarter.com/projects/1869987317/wish-i-was-here-1/widget/card.html</KICKSTARTER></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('kickstarter');
				}
			],
			[
				'http://www.kickstarter.com/projects/1869987317/wish-i-was-here-1/widget/video.html',
				'<r><KICKSTARTER id="1869987317/wish-i-was-here-1" url="http://www.kickstarter.com/projects/1869987317/wish-i-was-here-1/widget/video.html" video="video">http://www.kickstarter.com/projects/1869987317/wish-i-was-here-1/widget/video.html</KICKSTARTER></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('kickstarter');
				}
			],
			[
				'http://www.kissvideo.click/alton-towers-smiler-rollercoaster-crash_7789d8de8.html',
				'<r><KISSVIDEO id="7789d8de8" url="http://www.kissvideo.click/alton-towers-smiler-rollercoaster-crash_7789d8de8.html">http://www.kissvideo.click/alton-towers-smiler-rollercoaster-crash_7789d8de8.html</KISSVIDEO></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('kissvideo');
				}
			],
			[
				'https://www.livecap.tv/s/esl_sc2/uZoEz6RR1eA',
				'<r><LIVECAP channel="esl_sc2" id="uZoEz6RR1eA" url="https://www.livecap.tv/s/esl_sc2/uZoEz6RR1eA">https://www.livecap.tv/s/esl_sc2/uZoEz6RR1eA</LIVECAP></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('livecap');
				}
			],
			[
				'https://www.livecap.tv/t/riotgames/uLxUzBTBs7u',
				'<r><LIVECAP channel="riotgames" id="uLxUzBTBs7u" url="https://www.livecap.tv/t/riotgames/uLxUzBTBs7u">https://www.livecap.tv/t/riotgames/uLxUzBTBs7u</LIVECAP></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('livecap');
				}
			],
			[
				'http://www.liveleak.com/view?i=3dd_1366238099',
				'<r><LIVELEAK id="3dd_1366238099" url="http://www.liveleak.com/view?i=3dd_1366238099">http://www.liveleak.com/view?i=3dd_1366238099</LIVELEAK></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('liveleak');
				}
			],
			[
				'http://new.livestream.com/accounts/9999999999/events/9999999999',
				'<r><LIVESTREAM account_id="9999999999" event_id="9999999999" url="http://new.livestream.com/accounts/9999999999/events/9999999999">http://new.livestream.com/accounts/9999999999/events/9999999999</LIVESTREAM></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('livestream');
				}
			],
			[
				'https://medium.com/@donnydonny/team-internet-is-about-to-win-net-neutrality-and-they-didnt-need-googles-help-e7e2cf9b8a95',
				'<r><MEDIUM id="e7e2cf9b8a95" url="https://medium.com/@donnydonny/team-internet-is-about-to-win-net-neutrality-and-they-didnt-need-googles-help-e7e2cf9b8a95">https://medium.com/@donnydonny/team-internet-is-about-to-win-net-neutrality-and-they-didnt-need-googles-help-e7e2cf9b8a95</MEDIUM></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('medium');
				}
			],
			[
				'http://www.metacafe.com/watch/10785282/chocolate_treasure_chest_epic_meal_time/',
				'<r><METACAFE id="10785282" url="http://www.metacafe.com/watch/10785282/chocolate_treasure_chest_epic_meal_time/">http://www.metacafe.com/watch/10785282/chocolate_treasure_chest_epic_meal_time/</METACAFE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('metacafe');
				}
			],
			[
				'http://www.mixcloud.com/OneTakeTapes/timsch-one-take-tapes-2/',
				'<r><MIXCLOUD id="OneTakeTapes/timsch-one-take-tapes-2" url="http://www.mixcloud.com/OneTakeTapes/timsch-one-take-tapes-2/">http://www.mixcloud.com/OneTakeTapes/timsch-one-take-tapes-2/</MIXCLOUD></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('mixcloud');
				}
			],
			[
				'https://www.mixcloud.com/s2ck/dj-miiiiiit-mustern-drauf-guestmix-f%C3%BCr-kil-seine-party-liebe-gr%C3%BC%C3%9Fe-aus-freiburg/',
				'<r><MIXCLOUD id="s2ck/dj-miiiiiit-mustern-drauf-guestmix-f%C3%BCr-kil-seine-party-liebe-gr%C3%BC%C3%9Fe-aus-freiburg" url="https://www.mixcloud.com/s2ck/dj-miiiiiit-mustern-drauf-guestmix-f%C3%BCr-kil-seine-party-liebe-gr%C3%BC%C3%9Fe-aus-freiburg/">https://www.mixcloud.com/s2ck/dj-miiiiiit-mustern-drauf-guestmix-f%C3%BCr-kil-seine-party-liebe-gr%C3%BC%C3%9Fe-aus-freiburg/</MIXCLOUD></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('mixcloud');
				}
			],
			[
				'https://www.mixcloud.com/s2ck/dj-miiiiiit-mustern-drauf-guestmix-für-kil-seine-party-liebe-grüße-aus-freiburg/',
				'<r><MIXCLOUD id="s2ck/dj-miiiiiit-mustern-drauf-guestmix-für-kil-seine-party-liebe-grüße-aus-freiburg" url="https://www.mixcloud.com/s2ck/dj-miiiiiit-mustern-drauf-guestmix-f%C3%BCr-kil-seine-party-liebe-gr%C3%BC%C3%9Fe-aus-freiburg/">https://www.mixcloud.com/s2ck/dj-miiiiiit-mustern-drauf-guestmix-für-kil-seine-party-liebe-grüße-aus-freiburg/</MIXCLOUD></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('mixcloud');
				}
			],
			[
				'http://www.mixcloud.com/OneTakeTapes/timsch-one-take-tapes-2&foo=1/',
				'<t>http://www.mixcloud.com/OneTakeTapes/timsch-one-take-tapes-2&amp;foo=1/</t>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('mixcloud');
				}
			],
			[
				'http://www.mixcloud.com/categories/classical/',
				'<t>http://www.mixcloud.com/categories/classical/</t>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('mixcloud');
				}
			],
			[
				'http://www.mixcloud.com/tag/npr/',
				'<t>http://www.mixcloud.com/tag/npr/</t>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('mixcloud');
				}
			],
			[
				'http://m.mlb.com/video/v1205791883/hughes-and-coomer-call-baezs-seriesclinching-hit',
				'<r><MLB id="1205791883" url="http://m.mlb.com/video/v1205791883/hughes-and-coomer-call-baezs-seriesclinching-hit">http://m.mlb.com/video/v1205791883/hughes-and-coomer-call-baezs-seriesclinching-hit</MLB></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('mlb');
				}
			],
			[
				'http://m.mlb.com/col/video/topic/8877456/v1203589883/a-look-at-the-best-rockies-plays-from-2016/?c_id=col&partnerId=as_col_20161011_66746556&adbid=10154421890311113&adbpl=fb&adbpr=64466991112',
				'<r><MLB id="1203589883" url="http://m.mlb.com/col/video/topic/8877456/v1203589883/a-look-at-the-best-rockies-plays-from-2016/?c_id=col&amp;partnerId=as_col_20161011_66746556&amp;adbid=10154421890311113&amp;adbpl=fb&amp;adbpr=64466991112">http://m.mlb.com/col/video/topic/8877456/v1203589883/a-look-at-the-best-rockies-plays-from-2016/?c_id=col&amp;partnerId=as_col_20161011_66746556&amp;adbid=10154421890311113&amp;adbpl=fb&amp;adbpr=64466991112</MLB></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('mlb');
				}
			],
			[
				'http://mlb.mlb.com/r/video?content_id=1205791883',
				'<r><MLB id="1205791883" url="http://mlb.mlb.com/r/video?content_id=1205791883">http://mlb.mlb.com/r/video?content_id=1205791883</MLB></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('mlb');
				}
			],
			[
				'http://m.mlb.com/video/topic/7417714/v1202852683/ryan-dempster-believes-the-cubs-will-win-it-all',
				'<r><MLB id="1202852683" url="http://m.mlb.com/video/topic/7417714/v1202852683/ryan-dempster-believes-the-cubs-will-win-it-all">http://m.mlb.com/video/topic/7417714/v1202852683/ryan-dempster-believes-the-cubs-will-win-it-all</MLB></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('mlb');
				}
			],
			[
				'http://channel.nationalgeographic.com/channel/brain-games/videos/jason-silva-on-intuition/',
				'<r><NATGEOCHANNEL id="channel/brain-games/videos/jason-silva-on-intuition" url="http://channel.nationalgeographic.com/channel/brain-games/videos/jason-silva-on-intuition/">http://channel.nationalgeographic.com/channel/brain-games/videos/jason-silva-on-intuition/</NATGEOCHANNEL></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('natgeochannel');
					$configurator->MediaEmbed->add('natgeovideo');
				}
			],
			[
				'http://channel.nationalgeographic.com/wild/urban-jungle/videos/leopard-in-the-city/',
				'<r><NATGEOCHANNEL id="wild/urban-jungle/videos/leopard-in-the-city" url="http://channel.nationalgeographic.com/wild/urban-jungle/videos/leopard-in-the-city/">http://channel.nationalgeographic.com/wild/urban-jungle/videos/leopard-in-the-city/</NATGEOCHANNEL></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('natgeochannel');
					$configurator->MediaEmbed->add('natgeovideo');
				}
			],
			[
				'http://www.nbcnews.com/video/bob-dylan-awarded-nobel-prize-for-literature-785193027834',
				'<r><NBCNEWS id="785193027834" url="http://www.nbcnews.com/video/bob-dylan-awarded-nobel-prize-for-literature-785193027834">http://www.nbcnews.com/video/bob-dylan-awarded-nobel-prize-for-literature-785193027834</NBCNEWS></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('nbcnews');
				}
			],
			[
				'http://www.nbcnews.com/widget/video-embed/785160259969',
				'<r><NBCNEWS id="785160259969" url="http://www.nbcnews.com/widget/video-embed/785160259969">http://www.nbcnews.com/widget/video-embed/785160259969</NBCNEWS></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('nbcnews');
				}
			],
			[
				'https://www.nhl.com/video/korpikoski-scores-on-odd-man-rush/t-283069656/c-46322103',
				'<r><NHL c="46322103" t="283069656" url="https://www.nhl.com/video/korpikoski-scores-on-odd-man-rush/t-283069656/c-46322103">https://www.nhl.com/video/korpikoski-scores-on-odd-man-rush/t-283069656/c-46322103</NHL></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('nhl');
				}
			],
			[
				'https://www.nhl.com/video/c-46299003',
				'<r><NHL c="46299003" url="https://www.nhl.com/video/c-46299003">https://www.nhl.com/video/c-46299003</NHL></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('nhl');
				}
			],
			[
				'https://www.nhl.com/video/t-281748732?partnerId=as_nhl_20161104_67553116&adbid=794558584411426816&adbpl=tw&adbpr=50004938',
				'<r><NHL t="281748732" url="https://www.nhl.com/video/t-281748732?partnerId=as_nhl_20161104_67553116&amp;adbid=794558584411426816&amp;adbpl=tw&amp;adbpr=50004938">https://www.nhl.com/video/t-281748732?partnerId=as_nhl_20161104_67553116&amp;adbid=794558584411426816&amp;adbpl=tw&amp;adbpr=50004938</NHL></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('nhl');
				}
			],
			[
				'https://www.nhl.com/canadiens/video/radulovs-odd-empty-net-goal/t-277443720/c-45954203',
				'<r><NHL c="45954203" t="277443720" url="https://www.nhl.com/canadiens/video/radulovs-odd-empty-net-goal/t-277443720/c-45954203">https://www.nhl.com/canadiens/video/radulovs-odd-empty-net-goal/t-277443720/c-45954203</NHL></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('nhl');
				}
			],
			[
				'http://www.nytimes.com/video/technology/personaltech/100000002907606/soylent-taste-test.html',
				'<r><NYTIMES id="100000002907606" url="http://www.nytimes.com/video/technology/personaltech/100000002907606/soylent-taste-test.html">http://www.nytimes.com/video/technology/personaltech/100000002907606/soylent-taste-test.html</NYTIMES></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('nytimes');
				}
			],
			[
				'http://www.nytimes.com/video/2012/12/17/business/100000001950744/how-wal-mart-conquered-teotihuacan.html',
				'<r><NYTIMES id="100000001950744" url="http://www.nytimes.com/video/2012/12/17/business/100000001950744/how-wal-mart-conquered-teotihuacan.html">http://www.nytimes.com/video/2012/12/17/business/100000001950744/how-wal-mart-conquered-teotihuacan.html</NYTIMES></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('nytimes');
				}
			],
			[
				'http://www.nytimes.com/video/magazine/100000003166834/small-plates.html',
				'<r><NYTIMES id="100000003166834" url="http://www.nytimes.com/video/magazine/100000003166834/small-plates.html">http://www.nytimes.com/video/magazine/100000003166834/small-plates.html</NYTIMES></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('nytimes');
				}
			],
			[
				'https://oddshot.tv/s/-MrDaG',
				'<r><ODDSHOT id="-MrDaG" url="https://oddshot.tv/s/-MrDaG">https://oddshot.tv/s/-MrDaG</ODDSHOT></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('oddshot');
				}
			],
			[
				'http://pastebin.com/9jEf44nc',
				'<r><PASTEBIN id="9jEf44nc" url="http://pastebin.com/9jEf44nc">http://pastebin.com/9jEf44nc</PASTEBIN></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('pastebin');
				}
			],
			[
				'http://pastebin.com/u/username',
				'<t>http://pastebin.com/u/username</t>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('pastebin');
				}
			],
			[
				'http://pastebin.com/raw.php?i=9jEf44nc',
				'<r><PASTEBIN id="9jEf44nc" url="http://pastebin.com/raw.php?i=9jEf44nc">http://pastebin.com/raw.php?i=9jEf44nc</PASTEBIN></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('pastebin');
				}
			],
			[
				'http://pastebin.com/raw/9jEf44nc',
				'<r><PASTEBIN id="9jEf44nc" url="http://pastebin.com/raw/9jEf44nc">http://pastebin.com/raw/9jEf44nc</PASTEBIN></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('pastebin');
				}
			],
			[
				'https://www.pinterest.com/pin/99360735500167749/',
				'<r><PINTEREST id="99360735500167749" url="https://www.pinterest.com/pin/99360735500167749/">https://www.pinterest.com/pin/99360735500167749/</PINTEREST></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('pinterest');
				}
			],
			[
				'https://www.pinterest.com/pinterest/official-news/',
				'<r><PINTEREST id="pinterest/official-news" url="https://www.pinterest.com/pinterest/official-news/">https://www.pinterest.com/pinterest/official-news/</PINTEREST></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('pinterest');
				}
			],
			[
				'https://www.pinterest.com/pin/create/button/?url=',
				'<t>https://www.pinterest.com/pin/create/button/?url=</t>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('pinterest');
				}
			],
			[
				'https://www.pinterest.com/explore/business-smart-dress-code/?lp=true',
				'<t>https://www.pinterest.com/explore/business-smart-dress-code/?lp=true</t>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('pinterest');
				}
			],
			[
				'http://plays.tv/video/565683db95f139f47e/full-length-version-radeon-software-crimson-edition-is-amds-revolutionary-new-graphics-software-that',
				'<r><PLAYSTV id="565683db95f139f47e" url="http://plays.tv/video/565683db95f139f47e/full-length-version-radeon-software-crimson-edition-is-amds-revolutionary-new-graphics-software-that">http://plays.tv/video/565683db95f139f47e/full-length-version-radeon-software-crimson-edition-is-amds-revolutionary-new-graphics-software-that</PLAYSTV></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('playstv');
				}
			],
			[
				'http://www.podbean.com/media/share/pb-qtwub-4ee10c',
				'<r><PODBEAN id="qtwub-4ee10c" url="http://www.podbean.com/media/share/pb-qtwub-4ee10c">http://www.podbean.com/media/share/pb-qtwub-4ee10c</PODBEAN></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('podbean');
				}
			],
			[
				'http://prezi.com/5ye8po_hmikp/10-most-common-rookie-presentation-mistakes/',
				'<r><PREZI id="5ye8po_hmikp" url="http://prezi.com/5ye8po_hmikp/10-most-common-rookie-presentation-mistakes/">http://prezi.com/5ye8po_hmikp/10-most-common-rookie-presentation-mistakes/</PREZI></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('prezi');
				}
			],
			[
				'http://blog.prezi.com/latest/2014/2/7/10-most-common-rookie-mistakes-in-public-speaking.html/',
				'<t>http://blog.prezi.com/latest/2014/2/7/10-most-common-rookie-mistakes-in-public-speaking.html/</t>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('prezi');
				}
			],
			[
				'http://prezi.com/explore/staff-picks/',
				'<t>http://prezi.com/explore/staff-picks/</t>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('prezi');
				}
			],
			[
				'http://www.reddit.com/r/pics/comments/304rms/cats_reaction_to_seeing_the_ceiling_fan_move_for/cpp2kkl',
				'<r><REDDIT path="/r/pics/comments/304rms/cats_reaction_to_seeing_the_ceiling_fan_move_for/cpp2kkl" url="http://www.reddit.com/r/pics/comments/304rms/cats_reaction_to_seeing_the_ceiling_fan_move_for/cpp2kkl">http://www.reddit.com/r/pics/comments/304rms/cats_reaction_to_seeing_the_ceiling_fan_move_for/cpp2kkl</REDDIT></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('reddit');
				}
			],
			[
				'http://rutube.ru/tracks/4118278.html?v=8b490a46447720d4ad74616f5de2affd',
				'<r><RUTUBE id="4118278" url="http://rutube.ru/tracks/4118278.html?v=8b490a46447720d4ad74616f5de2affd">http://rutube.ru/tracks/4118278.html?v=8b490a46447720d4ad74616f5de2affd</RUTUBE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('rutube');
				}
			],
			[
				'http://www.scribd.com/doc/233658242/Detect-Malware-w-Memory-Forensics',
				'<r><SCRIBD id="233658242" url="http://www.scribd.com/doc/233658242/Detect-Malware-w-Memory-Forensics">http://www.scribd.com/doc/233658242/Detect-Malware-w-Memory-Forensics</SCRIBD></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('scribd');
				}
			],
			[
				'https://www.scribd.com/document/237147661/Calculus-2-Test-1-Review?in_collection=5291376',
				'<r><SCRIBD id="237147661" url="https://www.scribd.com/document/237147661/Calculus-2-Test-1-Review?in_collection=5291376">https://www.scribd.com/document/237147661/Calculus-2-Test-1-Review?in_collection=5291376</SCRIBD></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('scribd');
				}
			],
			[
				'https://www.scribd.com/mobile/document/318498911/Kerbin-Times-8',
				'<r><SCRIBD id="318498911" url="https://www.scribd.com/mobile/document/318498911/Kerbin-Times-8">https://www.scribd.com/mobile/document/318498911/Kerbin-Times-8</SCRIBD></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('scribd');
				}
			],
			[
				'http://www.slideshare.net/Slideshare/how-23431564',
				'<r><SLIDESHARE id="23431564" url="http://www.slideshare.net/Slideshare/how-23431564">http://www.slideshare.net/Slideshare/how-23431564</SLIDESHARE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('slideshare');
				}
			],
			[
				// Taken from the "WordPress Code" button of the page
				'[soundcloud url="http://api.soundcloud.com/tracks/98282116" params="" width=" 100%" height="166" iframe="true" /]',
				'<r><SOUNDCLOUD id="tracks/98282116" track_id="98282116" url="http://api.soundcloud.com/tracks/98282116">[soundcloud url="http://api.soundcloud.com/tracks/98282116" params="" width=" 100%" height="166" iframe="true" /]</SOUNDCLOUD></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->createIndividualBBCodes = true;
					$configurator->MediaEmbed->add('soundcloud');
				}
			],
			[
				'[soundcloud url="https://api.soundcloud.com/playlists/1919974" width="100%" height="450" iframe="true" /]',
				'<r><SOUNDCLOUD id="playlists/1919974" playlist_id="1919974" url="https://api.soundcloud.com/playlists/1919974">[soundcloud url="https://api.soundcloud.com/playlists/1919974" width="100%" height="450" iframe="true" /]</SOUNDCLOUD></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->createIndividualBBCodes = true;
					$configurator->MediaEmbed->add('soundcloud');
				}
			],
			[
				'https://api.soundcloud.com/tracks/168988860?secret_token=s-GT9Cd',
				'<r><SOUNDCLOUD id="tracks/168988860" secret_token="s-GT9Cd" track_id="168988860" url="https://api.soundcloud.com/tracks/168988860?secret_token=s-GT9Cd">https://api.soundcloud.com/tracks/168988860?secret_token=s-GT9Cd</SOUNDCLOUD></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('soundcloud');
				}
			],
			[
				'spotify:track:5JunxkcjfCYcY7xJ29tLai',
				'<r><SPOTIFY uri="spotify:track:5JunxkcjfCYcY7xJ29tLai">spotify:track:5JunxkcjfCYcY7xJ29tLai</SPOTIFY></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('spotify');
				}
			],
			[
				'spotify:album:0coYJwk0uFvOXDkgMzQJMG',
				'<r><SPOTIFY uri="spotify:album:0coYJwk0uFvOXDkgMzQJMG">spotify:album:0coYJwk0uFvOXDkgMzQJMG</SPOTIFY></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('spotify');
				}
			],
			[
				'[spotify]spotify:trackset:PREFEREDTITLE:5Z7ygHQo02SUrFmcgpwsKW,1x6ACsKV4UdWS2FMuPFUiT,4bi73jCM02fMpkI11Lqmfe[/spotify]',
				'<r><SPOTIFY uri="spotify:trackset:PREFEREDTITLE:5Z7ygHQo02SUrFmcgpwsKW,1x6ACsKV4UdWS2FMuPFUiT,4bi73jCM02fMpkI11Lqmfe"><s>[spotify]</s>spotify:trackset:PREFEREDTITLE:5Z7ygHQo02SUrFmcgpwsKW,1x6ACsKV4UdWS2FMuPFUiT,4bi73jCM02fMpkI11Lqmfe<e>[/spotify]</e></SPOTIFY></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->createIndividualBBCodes = true;
					$configurator->MediaEmbed->add('spotify');
				}
			],
			[
				'https://play.spotify.com/user/commodore-64/playlist/33fewoc4vDuICqL2mX95PA',
				'<r><SPOTIFY path="user/commodore-64/playlist/33fewoc4vDuICqL2mX95PA" url="https://play.spotify.com/user/commodore-64/playlist/33fewoc4vDuICqL2mX95PA">https://play.spotify.com/user/commodore-64/playlist/33fewoc4vDuICqL2mX95PA</SPOTIFY></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('spotify');
				}
			],
			[
				'https://play.spotify.com/track/6acKqVtKngFXApjvXsU6mQ',
				'<r><SPOTIFY path="track/6acKqVtKngFXApjvXsU6mQ" url="https://play.spotify.com/track/6acKqVtKngFXApjvXsU6mQ">https://play.spotify.com/track/6acKqVtKngFXApjvXsU6mQ</SPOTIFY></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('spotify');
				}
			],
			[
				'http://store.steampowered.com/app/517160/',
				'<r><STEAMSTORE id="517160" url="http://store.steampowered.com/app/517160/">http://store.steampowered.com/app/517160/</STEAMSTORE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('steamstore');
				}
			],
			[
				'http://strawpoll.me/738091',
				'<r><STRAWPOLL id="738091" url="http://strawpoll.me/738091">http://strawpoll.me/738091</STRAWPOLL></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('strawpoll');
				}
			],
			[
				'http://streamable.com/e4d',
				'<r><STREAMABLE id="e4d" url="http://streamable.com/e4d">http://streamable.com/e4d</STREAMABLE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('streamable');
				}
			],
			[
				'http://teamcoco.com/video/73784/historian-a-scott-berg-serious-jibber-jabber-with-conan-obrien',
				'<r><TEAMCOCO id="73784" url="http://teamcoco.com/video/73784/historian-a-scott-berg-serious-jibber-jabber-with-conan-obrien">http://teamcoco.com/video/73784/historian-a-scott-berg-serious-jibber-jabber-with-conan-obrien</TEAMCOCO></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('teamcoco');
				}
			],
			[
				'http://www.ted.com/talks/eli_pariser_beware_online_filter_bubbles.html',
				'<r><TED id="talks/eli_pariser_beware_online_filter_bubbles.html" url="http://www.ted.com/talks/eli_pariser_beware_online_filter_bubbles.html">http://www.ted.com/talks/eli_pariser_beware_online_filter_bubbles.html</TED></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('ted');
				}
			],
			[
				'http://www.ted.com/talks/eli_pariser_beware_online_filter_bubbles/transcript',
				'<t>http://www.ted.com/talks/eli_pariser_beware_online_filter_bubbles/transcript</t>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('ted');
				}
			],
			[
				'http://www.theatlantic.com/video/index/358928/computer-vision-syndrome-and-you/',
				'<r><THEATLANTIC id="358928" url="http://www.theatlantic.com/video/index/358928/computer-vision-syndrome-and-you/">http://www.theatlantic.com/video/index/358928/computer-vision-syndrome-and-you/</THEATLANTIC></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('theatlantic');
				}
			],
			[
				'http://www.theguardian.com/world/video/2016/apr/07/tokyos-hedgehog-cafe-encourages-you-to-embrace-prickly-pets-video',
				'<r><THEGUARDIAN id="world/video/2016/apr/07/tokyos-hedgehog-cafe-encourages-you-to-embrace-prickly-pets-video" url="http://www.theguardian.com/world/video/2016/apr/07/tokyos-hedgehog-cafe-encourages-you-to-embrace-prickly-pets-video">http://www.theguardian.com/world/video/2016/apr/07/tokyos-hedgehog-cafe-encourages-you-to-embrace-prickly-pets-video</THEGUARDIAN></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('theguardian');
				}
			],
			[
				'http://www.theonion.com/video/nation-successfully-completes-mothers-day-by-918-a,35998/',
				'<r><THEONION id="35998" url="http://www.theonion.com/video/nation-successfully-completes-mothers-day-by-918-a,35998/">http://www.theonion.com/video/nation-successfully-completes-mothers-day-by-918-a,35998/</THEONION></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('theonion');
				}
			],
			[
				'http://tinypic.com/player.php?v=29x86j9&s=8',
				'<r><TINYPIC id="29x86j9" s="8" url="http://tinypic.com/player.php?v=29x86j9&amp;s=8">http://tinypic.com/player.php?v=29x86j9&amp;s=8</TINYPIC></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('tinypic');
				}
			],
			[
				'http://tinypic.com/r/29x86j9/8',
				'<r><TINYPIC id="29x86j9" s="8" url="http://tinypic.com/r/29x86j9/8">http://tinypic.com/r/29x86j9/8</TINYPIC></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('tinypic');
				}
			],
			[
				'http://www.tmz.com/videos/0_2pr9x3rb/',
				'<r><TMZ id="0_2pr9x3rb" url="http://www.tmz.com/videos/0_2pr9x3rb/">http://www.tmz.com/videos/0_2pr9x3rb/</TMZ></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('tmz');
				}
			],
//			[
//				'http://www.traileraddict.com/tags/musical',
//				'<t>http://www.traileraddict.com/tags/musical</t>',
//				[],
//				function ($configurator)
//				{
//					$configurator->MediaEmbed->add('traileraddict');
//				}
//			],
			[
				'http://www.twitch.tv/playstation/v/3589809',
				'<r><TWITCH channel="playstation" url="http://www.twitch.tv/playstation/v/3589809" video_id="3589809">http://www.twitch.tv/playstation/v/3589809</TWITCH></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('twitch');
				}
			],
			[
				'https://www.twitch.tv/videos/29415830',
				'<r><TWITCH url="https://www.twitch.tv/videos/29415830" video_id="29415830">https://www.twitch.tv/videos/29415830</TWITCH></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('twitch');
				}
			],
			[
				'https://clips.twitch.tv/twitch/HorribleWoodpeckerHassanChop',
				'<r><TWITCH channel="twitch" clip_id="HorribleWoodpeckerHassanChop" url="https://clips.twitch.tv/twitch/HorribleWoodpeckerHassanChop">https://clips.twitch.tv/twitch/HorribleWoodpeckerHassanChop</TWITCH></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('twitch');
				}
			],
			[
				'https://clips.twitch.tv/AcceptableCooperativeYogurtTwitchRPG',
				'<r><TWITCH clip_id="AcceptableCooperativeYogurtTwitchRPG" url="https://clips.twitch.tv/AcceptableCooperativeYogurtTwitchRPG">https://clips.twitch.tv/AcceptableCooperativeYogurtTwitchRPG</TWITCH></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('twitch');
				}
			],
			[
				'https://blog.twitch.tv/coming-soon-get-it-on-twitch-9c829cae6ac1',
				'<t>https://blog.twitch.tv/coming-soon-get-it-on-twitch-9c829cae6ac1</t>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('twitch');
				}
			],
			[
				'https://twitter.com/BarackObama/statuses/266031293945503744',
				'<r><TWITTER id="266031293945503744" url="https://twitter.com/BarackObama/statuses/266031293945503744">https://twitter.com/BarackObama/statuses/266031293945503744</TWITTER></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('twitter');
				}
			],
			[
				'https://twitter.com/BarackObama/status/266031293945503744',
				'<r><TWITTER id="266031293945503744" url="https://twitter.com/BarackObama/status/266031293945503744">https://twitter.com/BarackObama/status/266031293945503744</TWITTER></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('twitter');
				}
			],
			[
				'https://twitter.com/#!/BarackObama/status/266031293945503744',
				'<r><TWITTER id="266031293945503744" url="https://twitter.com/#!/BarackObama/status/266031293945503744">https://twitter.com/#!/BarackObama/status/266031293945503744</TWITTER></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('twitter');
				}
			],
			[
				'https://mobile.twitter.com/DerekTVShow/status/463372588690202624',
				'<r><TWITTER id="463372588690202624" url="https://mobile.twitter.com/DerekTVShow/status/463372588690202624">https://mobile.twitter.com/DerekTVShow/status/463372588690202624</TWITTER></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('twitter');
				}
			],
			[
				'http://vbox7.com/play:a87a6894c5',
				'<r><VBOX7 id="a87a6894c5" url="http://vbox7.com/play:a87a6894c5">http://vbox7.com/play:a87a6894c5</VBOX7></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('vbox7');
				}
			],
			[
				'http://www.vevo.com/watch/USUV71400682',
				'<r><VEVO id="USUV71400682" url="http://www.vevo.com/watch/USUV71400682">http://www.vevo.com/watch/USUV71400682</VEVO></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('vevo');
				}
			],
			[
				'http://www.vevo.com/watch/eminem/the-monster-explicit/USUV71302925',
				'<r><VEVO id="USUV71302925" url="http://www.vevo.com/watch/eminem/the-monster-explicit/USUV71302925">http://www.vevo.com/watch/eminem/the-monster-explicit/USUV71302925</VEVO></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('vevo');
				}
			],
			[
				'http://www.viagame.com/channels/hearthstone-championship/404917',
				'<r><VIAGAME id="404917" url="http://www.viagame.com/channels/hearthstone-championship/404917">http://www.viagame.com/channels/hearthstone-championship/404917</VIAGAME></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('viagame');
				}
			],
			[
				'http://www.videodetective.com/movies/deadpool/38876',
				'<r><VIDEODETECTIVE id="38876" url="http://www.videodetective.com/movies/deadpool/38876">http://www.videodetective.com/movies/deadpool/38876</VIDEODETECTIVE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('videodetective');
				}
			],
			[
				'http://www.videodetective.com/movies/NATURAL_BORN_KILLERS/trailer/P00005250.htm',
				'<r><VIDEODETECTIVE id="5250" url="http://www.videodetective.com/movies/NATURAL_BORN_KILLERS/trailer/P00005250.htm">http://www.videodetective.com/movies/NATURAL_BORN_KILLERS/trailer/P00005250.htm</VIDEODETECTIVE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('videodetective');
				}
			],
			[
				'http://videomega.tv/?ref=aPRKXgQdaD',
				'<r><VIDEOMEGA id="aPRKXgQdaD" url="http://videomega.tv/?ref=aPRKXgQdaD">http://videomega.tv/?ref=aPRKXgQdaD</VIDEOMEGA></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('videomega');
				}
			],
			[
				'http://vimeo.com/67207222',
				'<r><VIMEO id="67207222" url="http://vimeo.com/67207222">http://vimeo.com/67207222</VIMEO></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('vimeo');
				}
			],
			[
				'https://player.vimeo.com/video/125956083',
				'<r><VIMEO id="125956083" url="https://player.vimeo.com/video/125956083">https://player.vimeo.com/video/125956083</VIMEO></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('vimeo');
				}
			],
			[
				'http://www.ustream.tv/recorded/40771396',
				'<r><USTREAM url="http://www.ustream.tv/recorded/40771396" vid="40771396">http://www.ustream.tv/recorded/40771396</USTREAM></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('ustream');
				}
			],
			[
				'http://www.ustream.tv/explore/education',
				'<t>http://www.ustream.tv/explore/education</t>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('ustream');
				}
			],
			[
				'http://www.ustream.tv/upcoming',
				'<t>http://www.ustream.tv/upcoming</t>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('ustream');
				}
			],
			[
				'http://www.veoh.com/watch/v6335577TeB8kyNR',
				'<r><VEOH id="6335577TeB8kyNR" url="http://www.veoh.com/watch/v6335577TeB8kyNR">http://www.veoh.com/watch/v6335577TeB8kyNR</VEOH></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('veoh');
				}
			],
			[
				'http://www.veoh.com/m/watch.php?v=v6335577TeB8kyNR',
				'<r><VEOH id="6335577TeB8kyNR" url="http://www.veoh.com/m/watch.php?v=v6335577TeB8kyNR">http://www.veoh.com/m/watch.php?v=v6335577TeB8kyNR</VEOH></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('veoh');
				}
			],
			[
				'http://vimeo.com/channels/staffpicks/67207222',
				'<r><VIMEO id="67207222" url="http://vimeo.com/channels/staffpicks/67207222">http://vimeo.com/channels/staffpicks/67207222</VIMEO></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('vimeo');
				}
			],
			[
				'https://vine.co/v/bYwPIluIipH',
				'<r><VINE id="bYwPIluIipH" url="https://vine.co/v/bYwPIluIipH">https://vine.co/v/bYwPIluIipH</VINE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('vine');
				}
			],
			[
				'http://vocaroo.com/i/s0dRy3rZ47bf',
				'<r><VOCAROO id="s0dRy3rZ47bf" url="http://vocaroo.com/i/s0dRy3rZ47bf">http://vocaroo.com/i/s0dRy3rZ47bf</VOCAROO></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('vocaroo');
				}
			],
			[
				'http://www.vox.com/2015/7/21/9005857/ant-man-marvel-apology-review#ooid=ltbzJkdTpKpE-O6hOfD3YJew3t3MppXb',
				'<r><VOX id="ltbzJkdTpKpE-O6hOfD3YJew3t3MppXb" url="http://www.vox.com/2015/7/21/9005857/ant-man-marvel-apology-review#ooid=ltbzJkdTpKpE-O6hOfD3YJew3t3MppXb">http://www.vox.com/2015/7/21/9005857/ant-man-marvel-apology-review#ooid=ltbzJkdTpKpE-O6hOfD3YJew3t3MppXb</VOX></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('vox');
				}
			],
			[
				'https://www.washingtonpost.com/video/c/video/df229384-9216-11e6-bc00-1a9756d4111b',
				'<r><WASHINGTONPOST id="df229384-9216-11e6-bc00-1a9756d4111b" url="https://www.washingtonpost.com/video/c/video/df229384-9216-11e6-bc00-1a9756d4111b">https://www.washingtonpost.com/video/c/video/df229384-9216-11e6-bc00-1a9756d4111b</WASHINGTONPOST></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('washingtonpost');
				}
			],
			[
				'http://www.washingtonpost.com/video/world/aurora-display-lights-up-the-night-sky-over-finland/2016/10/14/df229384-9216-11e6-bc00-1a9756d4111b_video.html',
				'<r><WASHINGTONPOST id="df229384-9216-11e6-bc00-1a9756d4111b" url="http://www.washingtonpost.com/video/world/aurora-display-lights-up-the-night-sky-over-finland/2016/10/14/df229384-9216-11e6-bc00-1a9756d4111b_video.html">http://www.washingtonpost.com/video/world/aurora-display-lights-up-the-night-sky-over-finland/2016/10/14/df229384-9216-11e6-bc00-1a9756d4111b_video.html</WASHINGTONPOST></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('washingtonpost');
				}
			],
			[
				'http://www.worldstarhiphop.com/featured/71630',
				'<r><WSHH id="71630" url="http://www.worldstarhiphop.com/featured/71630">http://www.worldstarhiphop.com/featured/71630</WSHH></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('wshh');
				}
			],
			[
				'http://live.wsj.com/#!09FB2B3B-583E-4284-99D8-FEF6C23BE4E2',
				'<r><WSJ id="09FB2B3B-583E-4284-99D8-FEF6C23BE4E2" url="http://live.wsj.com/#!09FB2B3B-583E-4284-99D8-FEF6C23BE4E2">http://live.wsj.com/#!09FB2B3B-583E-4284-99D8-FEF6C23BE4E2</WSJ></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('wsj');
				}
			],
			[
				'http://live.wsj.com/video/seahawks-qb-russell-wilson-on-super-bowl-win/9B3DF790-9D20-442C-B564-51524B06FD26.html',
				'<r><WSJ id="9B3DF790-9D20-442C-B564-51524B06FD26" url="http://live.wsj.com/video/seahawks-qb-russell-wilson-on-super-bowl-win/9B3DF790-9D20-442C-B564-51524B06FD26.html">http://live.wsj.com/video/seahawks-qb-russell-wilson-on-super-bowl-win/9B3DF790-9D20-442C-B564-51524B06FD26.html</WSJ></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('wsj');
				}
			],
			[
				'http://live.wsj.com/video/seth-rogen-emotional-appeal-over-alzheimer/3885A1E1-D5DE-443A-AA45-6A8F6BB8FBD8.html?mod=trending_now_video_4#!3885A1E1-D5DE-443A-AA45-6A8F6BB8FBD8',
				'<r><WSJ id="3885A1E1-D5DE-443A-AA45-6A8F6BB8FBD8" url="http://live.wsj.com/video/seth-rogen-emotional-appeal-over-alzheimer/3885A1E1-D5DE-443A-AA45-6A8F6BB8FBD8.html?mod=trending_now_video_4#!3885A1E1-D5DE-443A-AA45-6A8F6BB8FBD8">http://live.wsj.com/video/seth-rogen-emotional-appeal-over-alzheimer/3885A1E1-D5DE-443A-AA45-6A8F6BB8FBD8.html?mod=trending_now_video_4#!3885A1E1-D5DE-443A-AA45-6A8F6BB8FBD8</WSJ></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('wsj');
				}
			],
			[
				'http://www.wsj.com/video/nba-players-primp-with-pedicures/9E476D54-6A60-4F3F-ABC1-411014552DE6.html',
				'<r><WSJ id="9E476D54-6A60-4F3F-ABC1-411014552DE6" url="http://www.wsj.com/video/nba-players-primp-with-pedicures/9E476D54-6A60-4F3F-ABC1-411014552DE6.html">http://www.wsj.com/video/nba-players-primp-with-pedicures/9E476D54-6A60-4F3F-ABC1-411014552DE6.html</WSJ></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('wsj');
				}
			],
			[
				'https://screen.yahoo.com/mr-short-term-memory-000000263.html',
				'<r><YAHOOSCREEN id="mr-short-term-memory-000000263" url="https://screen.yahoo.com/mr-short-term-memory-000000263.html">https://screen.yahoo.com/mr-short-term-memory-000000263.html</YAHOOSCREEN></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('yahooscreen');
				}
			],
			[
				'http://xboxclips.com/dizturbd/e3a2d685-3e9f-454f-89bf-54ddea8f29b3',
				'<r><XBOXCLIPS id="e3a2d685-3e9f-454f-89bf-54ddea8f29b3" url="http://xboxclips.com/dizturbd/e3a2d685-3e9f-454f-89bf-54ddea8f29b3" user="dizturbd">http://xboxclips.com/dizturbd/e3a2d685-3e9f-454f-89bf-54ddea8f29b3</XBOXCLIPS></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('xboxclips');
				}
			],
			[
				'http://xboxclips.com/Spl0inker/screenshots/ab54bfa2-b1c8-444f-94da-466b8283ffb9',
				'<t>http://xboxclips.com/Spl0inker/screenshots/ab54bfa2-b1c8-444f-94da-466b8283ffb9</t>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('xboxclips');
				}
			],
			[
				'http://xboxdvr.com/gamer/LOXITANE/video/12463958',
				'<r><XBOXDVR id="12463958" url="http://xboxdvr.com/gamer/LOXITANE/video/12463958" user="LOXITANE">http://xboxdvr.com/gamer/LOXITANE/video/12463958</XBOXDVR></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('xboxdvr');
				}
			],
			[
				'https://screen.yahoo.com/dana-carvey-snl-skits/church-chat-satan-000000502.html',
				'<r><YAHOOSCREEN id="church-chat-satan-000000502" url="https://screen.yahoo.com/dana-carvey-snl-skits/church-chat-satan-000000502.html">https://screen.yahoo.com/dana-carvey-snl-skits/church-chat-satan-000000502.html</YAHOOSCREEN></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('yahooscreen');
				}
			],
			[
				'http://v.youku.com/v_show/id_XNzQwNjcxNDM2.html',
				'<r><YOUKU id="XNzQwNjcxNDM2" url="http://v.youku.com/v_show/id_XNzQwNjcxNDM2.html">http://v.youku.com/v_show/id_XNzQwNjcxNDM2.html</YOUKU></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youku');
				}
			],
			[
				'http://m.youku.com/video/id_XNzQwNjcxNDM2.html',
				'<r><YOUKU id="XNzQwNjcxNDM2" url="http://m.youku.com/video/id_XNzQwNjcxNDM2.html">http://m.youku.com/video/id_XNzQwNjcxNDM2.html</YOUKU></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youku');
				}
			],
			[
				'[media=youtube]-cEzsCAzTak[/media]',
				'<r><YOUTUBE id="-cEzsCAzTak" url="-cEzsCAzTak"><s>[media=youtube]</s>-cEzsCAzTak<e>[/media]</e></YOUTUBE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'[media]http://www.youtube.com/watch?v=-cEzsCAzTak&feature=channel[/media]',
				'<r><YOUTUBE id="-cEzsCAzTak" url="http://www.youtube.com/watch?v=-cEzsCAzTak&amp;feature=channel"><s>[media]</s>http://www.youtube.com/watch?v=-cEzsCAzTak&amp;feature=channel<e>[/media]</e></YOUTUBE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'xx [media url=http://www.youtube.com/watch?v=-cEzsCAzTak] xx',
				'<r>xx <YOUTUBE id="-cEzsCAzTak" url="http://www.youtube.com/watch?v=-cEzsCAzTak">[media url=http://www.youtube.com/watch?v=-cEzsCAzTak]</YOUTUBE> xx</r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'xx [media url=http://www.youtube.com/watch?v=-cEzsCAzTak /] xx',
				'<r>xx <YOUTUBE id="-cEzsCAzTak" url="http://www.youtube.com/watch?v=-cEzsCAzTak">[media url=http://www.youtube.com/watch?v=-cEzsCAzTak /]</YOUTUBE> xx</r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'[YOUTUBE]-cEzsCAzTak[/YOUTUBE]',
				'<r><YOUTUBE id="-cEzsCAzTak" url="-cEzsCAzTak"><s>[YOUTUBE]</s>-cEzsCAzTak<e>[/YOUTUBE]</e></YOUTUBE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->createIndividualBBCodes = true;
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'[YOUTUBE]http://www.youtube.com/watch?v=-cEzsCAzTak&feature=channel[/YOUTUBE]',
				'<r><YOUTUBE id="-cEzsCAzTak" url="http://www.youtube.com/watch?v=-cEzsCAzTak&amp;feature=channel"><s>[YOUTUBE]</s>http://www.youtube.com/watch?v=-cEzsCAzTak&amp;feature=channel<e>[/YOUTUBE]</e></YOUTUBE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->createIndividualBBCodes = true;
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'[YOUTUBE]http://www.youtube.com/watch?feature=player_embedded&v=-cEzsCAzTak[/YOUTUBE]',
				'<r><YOUTUBE id="-cEzsCAzTak" url="http://www.youtube.com/watch?feature=player_embedded&amp;v=-cEzsCAzTak"><s>[YOUTUBE]</s>http://www.youtube.com/watch?feature=player_embedded&amp;v=-cEzsCAzTak<e>[/YOUTUBE]</e></YOUTUBE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->createIndividualBBCodes = true;
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'[YOUTUBE]http://www.youtube.com/v/-cEzsCAzTak[/YOUTUBE]',
				'<r><YOUTUBE id="-cEzsCAzTak" url="http://www.youtube.com/v/-cEzsCAzTak"><s>[YOUTUBE]</s>http://www.youtube.com/v/-cEzsCAzTak<e>[/YOUTUBE]</e></YOUTUBE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->createIndividualBBCodes = true;
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'[YOUTUBE]http://youtu.be/-cEzsCAzTak[/YOUTUBE]',
				'<r><YOUTUBE id="-cEzsCAzTak" url="http://youtu.be/-cEzsCAzTak"><s>[YOUTUBE]</s>http://youtu.be/-cEzsCAzTak<e>[/YOUTUBE]</e></YOUTUBE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->createIndividualBBCodes = true;
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'Check this: http://www.youtube.com/watch?v=-cEzsCAzTak and that: http://example.com',
				'<r>Check this: <YOUTUBE id="-cEzsCAzTak" url="http://www.youtube.com/watch?v=-cEzsCAzTak"><URL url="http://www.youtube.com/watch?v=-cEzsCAzTak">http://www.youtube.com/watch?v=-cEzsCAzTak</URL></YOUTUBE> and that: <URL url="http://example.com">http://example.com</URL></r>',
				[],
				function ($configurator)
				{
					$configurator->Autolink;
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'http://www.youtube.com/watch?feature=player_detailpage&v=9bZkp7q19f0#t=113',
				'<r><YOUTUBE id="9bZkp7q19f0" t="113" url="http://www.youtube.com/watch?feature=player_detailpage&amp;v=9bZkp7q19f0#t=113">http://www.youtube.com/watch?feature=player_detailpage&amp;v=9bZkp7q19f0#t=113</YOUTUBE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'http://www.youtube.com/watch?feature=player_detailpage&v=9bZkp7q19f0&t=113',
				'<r><YOUTUBE id="9bZkp7q19f0" t="113" url="http://www.youtube.com/watch?feature=player_detailpage&amp;v=9bZkp7q19f0&amp;t=113">http://www.youtube.com/watch?feature=player_detailpage&amp;v=9bZkp7q19f0&amp;t=113</YOUTUBE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'http://www.youtube.com/watch?v=wZZ7oFKsKzY&t=1h23m45s',
				'<r><YOUTUBE h="1" id="wZZ7oFKsKzY" m="23" s="45" url="http://www.youtube.com/watch?v=wZZ7oFKsKzY&amp;t=1h23m45s">http://www.youtube.com/watch?v=wZZ7oFKsKzY&amp;t=1h23m45s</YOUTUBE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'http://www.youtube.com/watch?v=wZZ7oFKsKzY&t=23m45s',
				'<r><YOUTUBE id="wZZ7oFKsKzY" m="23" s="45" url="http://www.youtube.com/watch?v=wZZ7oFKsKzY&amp;t=23m45s">http://www.youtube.com/watch?v=wZZ7oFKsKzY&amp;t=23m45s</YOUTUBE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'http://www.youtube.com/watch?v=wZZ7oFKsKzY&t=45s',
				'<r><YOUTUBE id="wZZ7oFKsKzY" t="45" url="http://www.youtube.com/watch?v=wZZ7oFKsKzY&amp;t=45s">http://www.youtube.com/watch?v=wZZ7oFKsKzY&amp;t=45s</YOUTUBE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'https://youtu.be/wI__53kBBKM?t=9h38m43s',
				'<r><YOUTUBE h="9" id="wI__53kBBKM" m="38" s="43" url="https://youtu.be/wI__53kBBKM?t=9h38m43s">https://youtu.be/wI__53kBBKM?t=9h38m43s</YOUTUBE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'https://youtu.be/wI__53kBBKM?t=9h38m',
				'<r><YOUTUBE h="9" id="wI__53kBBKM" m="38" url="https://youtu.be/wI__53kBBKM?t=9h38m">https://youtu.be/wI__53kBBKM?t=9h38m</YOUTUBE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'https://youtu.be/wI__53kBBKM?t=9h43s',
				'<r><YOUTUBE h="9" id="wI__53kBBKM" s="43" url="https://youtu.be/wI__53kBBKM?t=9h43s">https://youtu.be/wI__53kBBKM?t=9h43s</YOUTUBE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'https://youtu.be/wI__53kBBKM?t=9h',
				'<r><YOUTUBE h="9" id="wI__53kBBKM" url="https://youtu.be/wI__53kBBKM?t=9h">https://youtu.be/wI__53kBBKM?t=9h</YOUTUBE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'https://youtu.be/wI__53kBBKM?t=38m',
				'<r><YOUTUBE id="wI__53kBBKM" m="38" url="https://youtu.be/wI__53kBBKM?t=38m">https://youtu.be/wI__53kBBKM?t=38m</YOUTUBE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'http://www.youtube.com/watch?v=pC35x6iIPmo&list=PLOU2XLYxmsIIxJrlMIY5vYXAFcO5g83gA',
				'<r><YOUTUBE id="pC35x6iIPmo" list="PLOU2XLYxmsIIxJrlMIY5vYXAFcO5g83gA" url="http://www.youtube.com/watch?v=pC35x6iIPmo&amp;list=PLOU2XLYxmsIIxJrlMIY5vYXAFcO5g83gA">http://www.youtube.com/watch?v=pC35x6iIPmo&amp;list=PLOU2XLYxmsIIxJrlMIY5vYXAFcO5g83gA</YOUTUBE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'http://www.youtube.com/watch?v=pC35x6iIPmo&list=PLOU2XLYxmsIIxJrlMIY5vYXAFcO5g83gA#t=123',
				'<r><YOUTUBE id="pC35x6iIPmo" list="PLOU2XLYxmsIIxJrlMIY5vYXAFcO5g83gA" t="123" url="http://www.youtube.com/watch?v=pC35x6iIPmo&amp;list=PLOU2XLYxmsIIxJrlMIY5vYXAFcO5g83gA#t=123">http://www.youtube.com/watch?v=pC35x6iIPmo&amp;list=PLOU2XLYxmsIIxJrlMIY5vYXAFcO5g83gA#t=123</YOUTUBE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'http://www.youtube.com/watch_popup?v=qybUFnY7Y8w',
				'<r><YOUTUBE id="qybUFnY7Y8w" url="http://www.youtube.com/watch_popup?v=qybUFnY7Y8w">http://www.youtube.com/watch_popup?v=qybUFnY7Y8w</YOUTUBE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'http://www.youtube.com/attribution_link?a=JdfC0C9V6ZI&u=%2Fwatch%3Fv%3DEhxJLojIE_o%26feature%3Dshare',
				'<r><YOUTUBE id="EhxJLojIE_o" url="http://www.youtube.com/attribution_link?a=JdfC0C9V6ZI&amp;u=%2Fwatch%3Fv%3DEhxJLojIE_o%26feature%3Dshare">http://www.youtube.com/attribution_link?a=JdfC0C9V6ZI&amp;u=%2Fwatch%3Fv%3DEhxJLojIE_o%26feature%3Dshare</YOUTUBE></r>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
		];
	}

	public function getRenderingTests()
	{
		return [
			[
				'http://www.amazon.ca/gp/product/B00GQT1LNO/',
				'<span data-s9e-mediaembed="amazon" style="display:inline-block;width:100%;max-width:120px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:200%"><iframe allowfullscreen="" scrolling="no" style="border:0;height:100%;left:0;position:absolute;width:100%" src="//ws-na.amazon-adsystem.com/widgets/q?ServiceVersion=20070822&amp;OneJS=1&amp;Operation=GetAdHtml&amp;MarketPlace=CA&amp;ad_type=product_link&amp;tracking_id=&amp;marketplace=amazon&amp;region=CA&amp;asins=B00GQT1LNO&amp;show_border=true&amp;link_opens_in_new_window=true"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('amazon');
				}
			],
			[
				'http://www.amazon.ca/gp/product/B00GQT1LNO/',
				'<span data-s9e-mediaembed="amazon" style="display:inline-block;width:100%;max-width:120px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:200%"><iframe allowfullscreen="" scrolling="no" style="border:0;height:100%;left:0;position:absolute;width:100%" src="//ws-na.amazon-adsystem.com/widgets/q?ServiceVersion=20070822&amp;OneJS=1&amp;Operation=GetAdHtml&amp;MarketPlace=CA&amp;ad_type=product_link&amp;tracking_id=foo-20&amp;marketplace=amazon&amp;region=CA&amp;asins=B00GQT1LNO&amp;show_border=true&amp;link_opens_in_new_window=true"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('amazon');
					$configurator->rendering->parameters['AMAZON_ASSOCIATE_TAG_CA'] = 'foo-20';
				}
			],
			[
				'http://www.amazon.ca/gp/product/B00GQT1LNO/ http://www.amazon.de/Netgear-WN3100RP-100PES-Repeater-integrierte-Steckdose/dp/B00ET2LTE6/',
				'<span data-s9e-mediaembed="amazon" style="display:inline-block;width:100%;max-width:120px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:200%"><iframe allowfullscreen="" scrolling="no" style="border:0;height:100%;left:0;position:absolute;width:100%" src="//ws-na.amazon-adsystem.com/widgets/q?ServiceVersion=20070822&amp;OneJS=1&amp;Operation=GetAdHtml&amp;MarketPlace=CA&amp;ad_type=product_link&amp;tracking_id=foo-20&amp;marketplace=amazon&amp;region=CA&amp;asins=B00GQT1LNO&amp;show_border=true&amp;link_opens_in_new_window=true"></iframe></span></span> <span data-s9e-mediaembed="amazon" style="display:inline-block;width:100%;max-width:120px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:200%"><iframe allowfullscreen="" scrolling="no" style="border:0;height:100%;left:0;position:absolute;width:100%" src="//ws-eu.amazon-adsystem.com/widgets/q?ServiceVersion=20070822&amp;OneJS=1&amp;Operation=GetAdHtml&amp;MarketPlace=DE&amp;ad_type=product_link&amp;tracking_id=bar-20&amp;marketplace=amazon&amp;region=DE&amp;asins=B00ET2LTE6&amp;show_border=true&amp;link_opens_in_new_window=true"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('amazon');
					$configurator->rendering->parameters['AMAZON_ASSOCIATE_TAG_CA'] = 'foo-20';
					$configurator->rendering->parameters['AMAZON_ASSOCIATE_TAG_DE'] = 'bar-20';
				}
			],
			[
				'http://www.amazon.co.jp/gp/product/B003AKZ6I8/',
				'<span data-s9e-mediaembed="amazon" style="display:inline-block;width:100%;max-width:120px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:200%"><iframe allowfullscreen="" scrolling="no" style="border:0;height:100%;left:0;position:absolute;width:100%" src="//ws-fe.amazon-adsystem.com/widgets/q?ServiceVersion=20070822&amp;OneJS=1&amp;Operation=GetAdHtml&amp;MarketPlace=JP&amp;ad_type=product_link&amp;tracking_id=&amp;marketplace=amazon&amp;region=JP&amp;asins=B003AKZ6I8&amp;show_border=true&amp;link_opens_in_new_window=true"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('amazon');
				}
			],
			[
				'http://www.amazon.co.uk/gp/product/B00BET0NR6/',
				'<span data-s9e-mediaembed="amazon" style="display:inline-block;width:100%;max-width:120px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:200%"><iframe allowfullscreen="" scrolling="no" style="border:0;height:100%;left:0;position:absolute;width:100%" src="//ws-eu.amazon-adsystem.com/widgets/q?ServiceVersion=20070822&amp;OneJS=1&amp;Operation=GetAdHtml&amp;MarketPlace=GB&amp;ad_type=product_link&amp;tracking_id=&amp;marketplace=amazon&amp;region=GB&amp;asins=B00BET0NR6&amp;show_border=true&amp;link_opens_in_new_window=true"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('amazon');
				}
			],
			[
				'http://www.amazon.com/dp/B002MUC0ZY',
				'<span data-s9e-mediaembed="amazon" style="display:inline-block;width:100%;max-width:120px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:200%"><iframe allowfullscreen="" scrolling="no" style="border:0;height:100%;left:0;position:absolute;width:100%" src="//ws-na.amazon-adsystem.com/widgets/q?ServiceVersion=20070822&amp;OneJS=1&amp;Operation=GetAdHtml&amp;MarketPlace=US&amp;ad_type=product_link&amp;tracking_id=-20&amp;marketplace=amazon&amp;region=US&amp;asins=B002MUC0ZY&amp;show_border=true&amp;link_opens_in_new_window=true"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('amazon');
				}
			],
			[
				'http://www.amazon.de/Netgear-WN3100RP-100PES-Repeater-integrierte-Steckdose/dp/B00ET2LTE6/',
				'<span data-s9e-mediaembed="amazon" style="display:inline-block;width:100%;max-width:120px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:200%"><iframe allowfullscreen="" scrolling="no" style="border:0;height:100%;left:0;position:absolute;width:100%" src="//ws-eu.amazon-adsystem.com/widgets/q?ServiceVersion=20070822&amp;OneJS=1&amp;Operation=GetAdHtml&amp;MarketPlace=DE&amp;ad_type=product_link&amp;tracking_id=&amp;marketplace=amazon&amp;region=DE&amp;asins=B00ET2LTE6&amp;show_border=true&amp;link_opens_in_new_window=true"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('amazon');
				}
			],
			[
				'http://www.amazon.in/Vans-Unisex-Authentic-Midnight-Sneakers/dp/B01I3LNWQG/',
				'<span data-s9e-mediaembed="amazon" style="display:inline-block;width:100%;max-width:120px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:200%"><iframe allowfullscreen="" scrolling="no" style="border:0;height:100%;left:0;position:absolute;width:100%" src="//ws-in.amazon-adsystem.com/widgets/q?ServiceVersion=20070822&amp;OneJS=1&amp;Operation=GetAdHtml&amp;MarketPlace=IN&amp;ad_type=product_link&amp;tracking_id=&amp;marketplace=amazon&amp;region=IN&amp;asins=B01I3LNWQG&amp;show_border=true&amp;link_opens_in_new_window=true"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('amazon');
				}
			],
			[
				'http://www.amazon.es/Vans-OLD-SKOOL-BLACK-WHITE/dp/B000R3QPEA/',
				'<span data-s9e-mediaembed="amazon" style="display:inline-block;width:100%;max-width:120px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:200%"><iframe allowfullscreen="" scrolling="no" style="border:0;height:100%;left:0;position:absolute;width:100%" src="//rcm-eu.amazon-adsystem.com/e/cm?lt1=_blank&amp;bc1=FFFFFF&amp;bg1=FFFFFF&amp;fc1=000000&amp;lc1=0000FF&amp;p=8&amp;l=as1&amp;f=ifr&amp;asins=B000R3QPEA&amp;o=30&amp;t=es-20"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('amazon');
					$configurator->rendering->parameters['AMAZON_ASSOCIATE_TAG_ES'] = 'es-20';
				}
			],
			[
				'http://www.amazon.fr/Vans-Authentic-Baskets-mixte-adulte/dp/B005NIKPAY/',
				'<span data-s9e-mediaembed="amazon" style="display:inline-block;width:100%;max-width:120px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:200%"><iframe allowfullscreen="" scrolling="no" style="border:0;height:100%;left:0;position:absolute;width:100%" src="//ws-eu.amazon-adsystem.com/widgets/q?ServiceVersion=20070822&amp;OneJS=1&amp;Operation=GetAdHtml&amp;MarketPlace=FR&amp;ad_type=product_link&amp;tracking_id=&amp;marketplace=amazon&amp;region=FR&amp;asins=B005NIKPAY&amp;show_border=true&amp;link_opens_in_new_window=true"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('amazon');
				}
			],
			[
				'http://www.amazon.it/gp/product/B00JGOMIP6/',
				'<span data-s9e-mediaembed="amazon" style="display:inline-block;width:100%;max-width:120px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:200%"><iframe allowfullscreen="" scrolling="no" style="border:0;height:100%;left:0;position:absolute;width:100%" src="//rcm-eu.amazon-adsystem.com/e/cm?lt1=_blank&amp;bc1=FFFFFF&amp;bg1=FFFFFF&amp;fc1=000000&amp;lc1=0000FF&amp;p=8&amp;l=as1&amp;f=ifr&amp;asins=B00JGOMIP6&amp;o=29&amp;t=_"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('amazon');
				}
			],
			[
				'http://www.audiomack.com/album/hz-global/double-a-side-vol3',
				'<iframe data-s9e-mediaembed="audiomack" allowfullscreen="" scrolling="no" src="https://www.audiomack.com/embed/album/hz-global/double-a-side-vol3" style="border:0;height:400px;max-width:900px;width:100%"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('audiomack');
				}
			],
			[
				'http://www.audiomack.com/song/random-2/buy-the-world-final-1',
				'<iframe data-s9e-mediaembed="audiomack" allowfullscreen="" scrolling="no" src="https://www.audiomack.com/embed/song/random-2/buy-the-world-final-1" style="border:0;height:252px;max-width:900px;width:100%"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('audiomack');
				}
			],
			[
				'http://www.cbsnews.com/video/watch/?id=50156501n',
				'<span data-s9e-mediaembed="cbsnews" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:62.5%;padding-bottom:calc(56.25% + 40px)"><object data="//i.i.cbsi.com/cnwk.1d/av/video/cbsnews/atlantis2/cbsnews_player_embed.swf" style="height:100%;left:0;position:absolute;width:100%" type="application/x-shockwave-flash" typemustmatch=""><param name="allowfullscreen" value="true"><param name="flashvars" value="si=254&amp;contentValue=50156501"></object></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('cbsnews');
				}
			],
			[
				'http://www.collegehumor.com/video/1181601/more-than-friends',
				'<span data-s9e-mediaembed="collegehumor" style="display:inline-block;width:100%;max-width:600px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:61.5%"><iframe allowfullscreen="" scrolling="no" src="//www.collegehumor.com/e/1181601" style="border:0;height:100%;left:0;position:absolute;width:100%"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('collegehumor');
				}
			],
			[
				'http://www.dailymotion.com/video/x222z1',
				'<span data-s9e-mediaembed="dailymotion" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" src="//www.dailymotion.com/embed/video/x222z1" style="border:0;height:100%;left:0;position:absolute;width:100%"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('dailymotion');
				}
			],
			[
				'http://www.democracynow.org/2014/7/2/dn_at_almedalen_week_at_swedens',
				'<span data-s9e-mediaembed="democracynow" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" style="border:0;height:100%;left:0;position:absolute;width:100%" src="//www.democracynow.org/embed/story/2014/7/2/dn_at_almedalen_week_at_swedens"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('democracynow');
				}
			],
			[
				'http://www.democracynow.org/blog/2015/3/13/part_2_bruce_schneier_on_the',
				'<span data-s9e-mediaembed="democracynow" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" style="border:0;height:100%;left:0;position:absolute;width:100%" src="//www.democracynow.org/embed/blog/2015/3/13/part_2_bruce_schneier_on_the"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('democracynow');
				}
			],
			[
				'http://www.democracynow.org/shows/2006/2/20',
				'<span data-s9e-mediaembed="democracynow" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" style="border:0;height:100%;left:0;position:absolute;width:100%" src="//www.democracynow.org/embed/show/2006/2/20"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('democracynow');
				}
			],
			[
				'http://www.democracynow.org/2015/5/21/headlines',
				'<span data-s9e-mediaembed="democracynow" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" style="border:0;height:100%;left:0;position:absolute;width:100%" src="//www.democracynow.org/embed/headlines/2015/5/21"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('democracynow');
				}
			],
			[
				'http://www.dumpert.nl/mediabase/6622577/4652b140/r_mi_gaillard_doet_halloween_prank.html',
				'<span data-s9e-mediaembed="dumpert" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" src="//www.dumpert.nl/embed/6622577/4652b140/" style="border:0;height:100%;left:0;position:absolute;width:100%"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('dumpert');
				}
			],
			[
				'https://www.facebook.com/video/video.php?v=10100658170103643',
				'<iframe data-s9e-mediaembed="facebook" allowfullscreen="" onload="var a=Math.random();window.addEventListener(\'message\',function(b){if(b.data.id==a)style.height=b.data.height+\'px\'});contentWindow.postMessage(\'s9e:\'+a,\'https://s9e.github.io\')" scrolling="no" src="https://s9e.github.io/iframe/facebook.min.html#video10100658170103643" style="border:0;height:360px;max-width:640px;width:100%"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('facebook');
				}
			],
			[
				'https://www.facebook.com/FacebookDevelopers/posts/10151471074398553',
				'<iframe data-s9e-mediaembed="facebook" allowfullscreen="" onload="var a=Math.random();window.addEventListener(\'message\',function(b){if(b.data.id==a)style.height=b.data.height+\'px\'});contentWindow.postMessage(\'s9e:\'+a,\'https://s9e.github.io\')" scrolling="no" src="https://s9e.github.io/iframe/facebook.min.html#10151471074398553" style="border:0;height:360px;max-width:640px;width:100%"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('facebook');
				}
			],
			[
				'http://www.funnyordie.com/videos/bf313bd8b4/murdock-with-keith-david',
				'<span data-s9e-mediaembed="funnyordie" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" src="//www.funnyordie.com/embed/bf313bd8b4" style="border:0;height:100%;left:0;position:absolute;width:100%"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('funnyordie');
				}
			],
			[
				'http://www.gamespot.com/destiny/videos/destiny-the-moon-trailer-6415176/',
				'<span data-s9e-mediaembed="gamespot" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:62.5%"><iframe allowfullscreen="" scrolling="no" src="//www.gamespot.com/videos/embed/6415176/" style="border:0;height:100%;left:0;position:absolute;width:100%"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('gamespot');
				}
			],
			[
				'https://gist.github.com/s9e/6806305',
				'<iframe data-s9e-mediaembed="gist" allowfullscreen="" onload="var a=Math.random();window.addEventListener(\'message\',function(b){if(b.data.id==a)style.height=b.data.height+\'px\'});contentWindow.postMessage(\'s9e:\'+a,\'https://s9e.github.io\')" scrolling="" src="https://s9e.github.io/iframe/gist.min.html#s9e/6806305" style="border:0;height:180px;width:100%"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('gist');
				}
			],
			[
				'https://plus.google.com/110286587261352351537/posts/XMABm8rLvRW',
				'<iframe data-s9e-mediaembed="googleplus" allowfullscreen="" onload="var a=Math.random();window.addEventListener(\'message\',function(b){if(b.data.id==a)style.height=b.data.height+\'px\'});contentWindow.postMessage(\'s9e:\'+a,\'https://s9e.github.io\')" scrolling="no" style="border:0;height:240px;max-width:450px;width:100%" src="https://s9e.github.io/iframe/googleplus.min.html#110286587261352351537/posts/XMABm8rLvRW"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('googleplus');
				}
			],
			[
				'https://plus.google.com/+TonyHawk/posts/C5TMsDZJWBd',
				'<iframe data-s9e-mediaembed="googleplus" allowfullscreen="" onload="var a=Math.random();window.addEventListener(\'message\',function(b){if(b.data.id==a)style.height=b.data.height+\'px\'});contentWindow.postMessage(\'s9e:\'+a,\'https://s9e.github.io\')" scrolling="no" style="border:0;height:240px;max-width:450px;width:100%" src="https://s9e.github.io/iframe/googleplus.min.html#+TonyHawk/posts/C5TMsDZJWBd"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('googleplus');
				}
			],
			[
				'http://uk.ign.com/videos/2013/07/12/pokemon-x-version-pokemon-y-version-battle-trailer',
				'<span data-s9e-mediaembed="ign" style="display:inline-block;width:100%;max-width:468px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.196581%"><iframe allowfullscreen="" scrolling="no" src="//widgets.ign.com/video/embed/content.html?url=http://uk.ign.com/videos/2013/07/12/pokemon-x-version-pokemon-y-version-battle-trailer" style="border:0;height:100%;left:0;position:absolute;width:100%"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('ign');
				}
			],
			[
				'https://imgur.com/AsQ0K3P',
				'<iframe data-s9e-mediaembed="imgur" allowfullscreen="" onload="var b=Math.random();window.addEventListener(\'message\',function(a){a.data.id==b&amp;&amp;(style.height=a.data.height+\'px\',style.width=a.data.width+\'px\')});contentWindow.postMessage(\'s9e:\'+b,\'https://s9e.github.io\')" scrolling="no" style="border:0;height:450px;max-width:100%;width:568px" src="https://s9e.github.io/iframe/imgur.min.html#AsQ0K3P"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('imgur');
				}
			],
			[
				'http://i.imgur.com/u7Yo0Vy.gifv',
				'<iframe data-s9e-mediaembed="imgur" allowfullscreen="" onload="var b=Math.random();window.addEventListener(\'message\',function(a){a.data.id==b&amp;&amp;(style.height=a.data.height+\'px\',style.width=a.data.width+\'px\')});contentWindow.postMessage(\'s9e:\'+b,\'https://s9e.github.io\')" scrolling="no" style="border:0;height:450px;max-width:100%;width:568px" src="https://s9e.github.io/iframe/imgur.min.html#u7Yo0Vy"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('imgur');
				}
			],
			[
				'http://i.imgur.com/u7Yo0Vy.mp4',
				'<iframe data-s9e-mediaembed="imgur" allowfullscreen="" onload="var b=Math.random();window.addEventListener(\'message\',function(a){a.data.id==b&amp;&amp;(style.height=a.data.height+\'px\',style.width=a.data.width+\'px\')});contentWindow.postMessage(\'s9e:\'+b,\'https://s9e.github.io\')" scrolling="no" style="border:0;height:450px;max-width:100%;width:568px" src="https://s9e.github.io/iframe/imgur.min.html#u7Yo0Vy"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('imgur');
				}
			],
			[
				'https://imgur.com/a/0I30l',
				'<iframe data-s9e-mediaembed="imgur" allowfullscreen="" onload="var b=Math.random();window.addEventListener(\'message\',function(a){a.data.id==b&amp;&amp;(style.height=a.data.height+\'px\',style.width=a.data.width+\'px\')});contentWindow.postMessage(\'s9e:\'+b,\'https://s9e.github.io\')" scrolling="no" style="border:0;height:450px;max-width:100%;width:568px" src="https://s9e.github.io/iframe/imgur.min.html#a/0I30l"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('imgur');
				}
			],
			[
				'http://www.indiegogo.com/projects/513633',
				'<span data-s9e-mediaembed="indiegogo" style="display:inline-block;width:100%;max-width:222px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:200.45045%"><iframe allowfullscreen="" scrolling="no" src="//www.indiegogo.com/project/513633/embedded" style="border:0;height:100%;left:0;position:absolute;width:100%"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('indiegogo');
				}
			],
			[
				'http://www.kickstarter.com/projects/1869987317/wish-i-was-here-1?ref=',
				'<span data-s9e-mediaembed="kickstarter" style="display:inline-block;width:100%;max-width:220px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:190.909091%"><iframe allowfullscreen="" scrolling="no" src="//www.kickstarter.com/projects/1869987317/wish-i-was-here-1/widget/card.html" style="border:0;height:100%;left:0;position:absolute;width:100%"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('kickstarter');
				}
			],
			[
				'http://www.kickstarter.com/projects/1869987317/wish-i-was-here-1/widget/video.html',
				'<span data-s9e-mediaembed="kickstarter" style="display:inline-block;width:100%;max-width:480px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:75%"><iframe allowfullscreen="" scrolling="no" src="//www.kickstarter.com/projects/1869987317/wish-i-was-here-1/widget/video.html" style="border:0;height:100%;left:0;position:absolute;width:100%"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('kickstarter');
				}
			],
			[
				'http://www.liveleak.com/view?i=3dd_1366238099',
				'<span data-s9e-mediaembed="liveleak" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" src="//www.liveleak.com/ll_embed?i=3dd_1366238099" style="border:0;height:100%;left:0;position:absolute;width:100%"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('liveleak');
				}
			],
			[
				'https://medium.com/@donnydonny/team-internet-is-about-to-win-net-neutrality-and-they-didnt-need-googles-help-e7e2cf9b8a95',
				'<iframe data-s9e-mediaembed="medium" allowfullscreen="" onload="window.addEventListener(\'message\',function(a){a=a.data.split(\'::\');\'m\'===a[0]&amp;&amp;0&lt;src.indexOf(a[1])&amp;&amp;a[2]&amp;&amp;(style.height=a[2]+\'px\')})" scrolling="no" src="https://api.medium.com/embed?type=story&amp;path=%2F%2Fe7e2cf9b8a95&amp;id=171211918195" style="border:1px solid;border-color:#eee #ddd #bbb;border-radius:5px;box-shadow:rgba(0,0,0,.15) 0 1px 3px;height:400px;max-width:400px;width:100%"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('medium');
				}
			],
			[
				'http://www.metacafe.com/watch/10785282/chocolate_treasure_chest_epic_meal_time/',
				'<span data-s9e-mediaembed="metacafe" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" src="//www.metacafe.com/embed/10785282/" style="border:0;height:100%;left:0;position:absolute;width:100%"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('metacafe');
				}
			],
			[
				'https://www.nhl.com/video/korpikoski-scores-on-odd-man-rush/t-283069656/c-46322103',
				'<span data-s9e-mediaembed="nhl" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" style="border:0;height:100%;left:0;position:absolute;width:100%" src="https://www.nhl.com/video/embed/t-283069656/c-46322103?autostart=false"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('nhl');
				}
			],
			[
				'https://www.nhl.com/video/c-46299003',
				'<span data-s9e-mediaembed="nhl" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" style="border:0;height:100%;left:0;position:absolute;width:100%" src="https://www.nhl.com/video/embed/c-46299003?autostart=false"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('nhl');
				}
			],
			[
				'https://www.nhl.com/video/t-281748732?partnerId=as_nhl_20161104_67553116&adbid=794558584411426816&adbpl=tw&adbpr=50004938',
				'<span data-s9e-mediaembed="nhl" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" style="border:0;height:100%;left:0;position:absolute;width:100%" src="https://www.nhl.com/video/embed/t-281748732?autostart=false"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('nhl');
				}
			],
			[
				'http://rutube.ru/tracks/4118278.html?v=8b490a46447720d4ad74616f5de2affd',
				'<span data-s9e-mediaembed="rutube" style="display:inline-block;width:100%;max-width:720px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" src="//rutube.ru/play/embed/4118278" style="border:0;height:100%;left:0;position:absolute;width:100%"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('rutube');
				}
			],
			[
				'http://www.slideshare.net/Slideshare/how-23431564',
				'<span data-s9e-mediaembed="slideshare" style="display:inline-block;width:100%;max-width:427px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:83.372365%"><iframe allowfullscreen="" scrolling="no" src="//www.slideshare.net/slideshow/embed_code/23431564" style="border:0;height:100%;left:0;position:absolute;width:100%"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('slideshare');
				}
			],
			[
				// Taken from the "WordPress Code" button of the page
				'[soundcloud url="http://api.soundcloud.com/tracks/98282116" params="" width=" 100%" height="166" iframe="true" /]',
				'<iframe data-s9e-mediaembed="soundcloud" allowfullscreen="" scrolling="no" src="https://w.soundcloud.com/player/?url=https%3A//api.soundcloud.com/tracks/98282116&amp;secret_token=" style="border:0;height:166px;max-width:900px;width:100%"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->createIndividualBBCodes = true;
					$configurator->MediaEmbed->add('soundcloud');
				}
			],
			[
				'[soundcloud url="https://api.soundcloud.com/playlists/1919974" width="100%" height="450" iframe="true" /]',
				'<iframe data-s9e-mediaembed="soundcloud" allowfullscreen="" scrolling="no" src="https://w.soundcloud.com/player/?url=https%3A//api.soundcloud.com/playlists/1919974" style="border:0;height:450px;max-width:900px;width:100%"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->createIndividualBBCodes = true;
					$configurator->MediaEmbed->add('soundcloud');
				}
			],
			[
				'[spotify]spotify:track:5JunxkcjfCYcY7xJ29tLai[/spotify]',
				'<span data-s9e-mediaembed="spotify" style="display:inline-block;width:100%;max-width:400px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:120%"><iframe allowfullscreen="" scrolling="no" style="border:0;height:100%;left:0;position:absolute;width:100%" src="https://embed.spotify.com/?view=coverart&amp;uri=spotify:track:5JunxkcjfCYcY7xJ29tLai"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->createIndividualBBCodes = true;
					$configurator->MediaEmbed->add('spotify');
				}
			],
			[
				'https://play.spotify.com/album/5OSzFvFAYuRh93WDNCTLEz',
				'<span data-s9e-mediaembed="spotify" style="display:inline-block;width:100%;max-width:400px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:120%"><iframe allowfullscreen="" scrolling="no" style="border:0;height:100%;left:0;position:absolute;width:100%" src="https://embed.spotify.com/?view=coverart&amp;uri=spotify:album:5OSzFvFAYuRh93WDNCTLEz"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('spotify');
				}
			],
			[
				'https://play.spotify.com/track/3lDpjvbifbmrmzWGE8F9zd',
				'<span data-s9e-mediaembed="spotify" style="display:inline-block;width:100%;max-width:400px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:120%"><iframe allowfullscreen="" scrolling="no" style="border:0;height:100%;left:0;position:absolute;width:100%" src="https://embed.spotify.com/?view=coverart&amp;uri=spotify:track:3lDpjvbifbmrmzWGE8F9zd"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('spotify');
				}
			],
			[
				'http://www.ted.com/talks/eli_pariser_beware_online_filter_bubbles.html',
				'<span data-s9e-mediaembed="ted" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" style="border:0;height:100%;left:0;position:absolute;width:100%" src="//embed.ted.com/talks/eli_pariser_beware_online_filter_bubbles.html"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('ted');
				}
			],
			[
				'http://www.ted.com/talks/richard_ledgett_the_nsa_responds_to_edward_snowden_s_ted_talk',
				'<span data-s9e-mediaembed="ted" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" style="border:0;height:100%;left:0;position:absolute;width:100%" src="//embed.ted.com/talks/richard_ledgett_the_nsa_responds_to_edward_snowden_s_ted_talk.html"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('ted');
				}
			],
			[
				'http://www.twitch.tv/twitch',
				'<span data-s9e-mediaembed="twitch" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" style="border:0;height:100%;left:0;position:absolute;width:100%" src="//player.twitch.tv/?autoplay=false&amp;channel=twitch"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('twitch');
				}
			],
			[
				'http://www.twitch.tv/twitch/v/29415830',
				'<span data-s9e-mediaembed="twitch" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" style="border:0;height:100%;left:0;position:absolute;width:100%" src="//player.twitch.tv/?autoplay=false&amp;video=v29415830"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('twitch');
				}
			],
			[
				'http://www.twitch.tv/twitch/v/29415830?t=17m17s',
				'<span data-s9e-mediaembed="twitch" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" style="border:0;height:100%;left:0;position:absolute;width:100%" src="//player.twitch.tv/?autoplay=false&amp;video=v29415830&amp;time=17m17s"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('twitch');
				}
			],
			[
				'https://clips.twitch.tv/twitch/HorribleWoodpeckerHassanChop',
				'<span data-s9e-mediaembed="twitch" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" style="border:0;height:100%;left:0;position:absolute;width:100%" src="//clips.twitch.tv/embed?autoplay=false&amp;clip=twitch/HorribleWoodpeckerHassanChop"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('twitch');
				}
			],
			[
				'https://clips.twitch.tv/AcceptableCooperativeYogurtTwitchRPG',
				'<span data-s9e-mediaembed="twitch" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" style="border:0;height:100%;left:0;position:absolute;width:100%" src="//clips.twitch.tv/embed?autoplay=false&amp;clip=AcceptableCooperativeYogurtTwitchRPG"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('twitch');
				}
			],
			[
				'https://twitter.com/BarackObama/statuses/266031293945503744',
				'<iframe data-s9e-mediaembed="twitter" allowfullscreen="" onload="var a=Math.random();window.addEventListener(\'message\',function(b){if(b.data.id==a)style.height=b.data.height+\'px\'});contentWindow.postMessage(\'s9e:\'+a,\'https://s9e.github.io\')" scrolling="no" src="https://s9e.github.io/iframe/twitter.min.html#266031293945503744" style="background:url(https://abs.twimg.com/favicons/favicon.ico) no-repeat 50% 50%;border:0;height:186px;max-width:500px;width:100%"></iframe>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('twitter');
				}
			],
			[
				'http://www.ustream.tv/recorded/40771396',
				'<span data-s9e-mediaembed="ustream" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" src="//www.ustream.tv/embed/recorded/40771396?html5ui" style="border:0;height:100%;left:0;position:absolute;width:100%"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('ustream');
				}
			],
			[
				'http://vimeo.com/67207222',
				'<span data-s9e-mediaembed="vimeo" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" src="//player.vimeo.com/video/67207222" style="border:0;height:100%;left:0;position:absolute;width:100%"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('vimeo');
				}
			],
			[
				'https://vine.co/v/bYwPIluIipH',
				'<span data-s9e-mediaembed="vine" style="display:inline-block;width:100%;max-width:480px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:100%"><iframe allowfullscreen="" scrolling="no" src="https://vine.co/v/bYwPIluIipH/embed/simple?audio=1" style="border:0;height:100%;left:0;position:absolute;width:100%"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('vine');
				}
			],
			[
				'[media=youtube]-cEzsCAzTak[/media]',
				'<span data-s9e-mediaembed="youtube" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" style="background:url(https://i.ytimg.com/vi/-cEzsCAzTak/hqdefault.jpg) 50% 50% / cover;border:0;height:100%;left:0;position:absolute;width:100%" src="https://www.youtube.com/embed/-cEzsCAzTak"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'[media]http://www.youtube.com/watch?v=-cEzsCAzTak&feature=channel[/media]',
				'<span data-s9e-mediaembed="youtube" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" style="background:url(https://i.ytimg.com/vi/-cEzsCAzTak/hqdefault.jpg) 50% 50% / cover;border:0;height:100%;left:0;position:absolute;width:100%" src="https://www.youtube.com/embed/-cEzsCAzTak"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'[YOUTUBE]-cEzsCAzTak[/YOUTUBE]',
				'<span data-s9e-mediaembed="youtube" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" style="background:url(https://i.ytimg.com/vi/-cEzsCAzTak/hqdefault.jpg) 50% 50% / cover;border:0;height:100%;left:0;position:absolute;width:100%" src="https://www.youtube.com/embed/-cEzsCAzTak"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->createIndividualBBCodes = true;
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'[YOUTUBE]http://www.youtube.com/watch?v=-cEzsCAzTak&feature=channel[/YOUTUBE]',
				'<span data-s9e-mediaembed="youtube" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" style="background:url(https://i.ytimg.com/vi/-cEzsCAzTak/hqdefault.jpg) 50% 50% / cover;border:0;height:100%;left:0;position:absolute;width:100%" src="https://www.youtube.com/embed/-cEzsCAzTak"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->createIndividualBBCodes = true;
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'[YOUTUBE=http://www.youtube.com/watch?v=-cEzsCAzTak]Hi!',
				'<span data-s9e-mediaembed="youtube" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" style="background:url(https://i.ytimg.com/vi/-cEzsCAzTak/hqdefault.jpg) 50% 50% / cover;border:0;height:100%;left:0;position:absolute;width:100%" src="https://www.youtube.com/embed/-cEzsCAzTak"></iframe></span></span>Hi!',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->createIndividualBBCodes = true;
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'Check this: http://www.youtube.com/watch?v=-cEzsCAzTak',
				'Check this: <span data-s9e-mediaembed="youtube" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" style="background:url(https://i.ytimg.com/vi/-cEzsCAzTak/hqdefault.jpg) 50% 50% / cover;border:0;height:100%;left:0;position:absolute;width:100%" src="https://www.youtube.com/embed/-cEzsCAzTak"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'Check this: http://www.youtube.com/watch?v=-cEzsCAzTak and that: http://example.com',
				'Check this: <span data-s9e-mediaembed="youtube" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" style="background:url(https://i.ytimg.com/vi/-cEzsCAzTak/hqdefault.jpg) 50% 50% / cover;border:0;height:100%;left:0;position:absolute;width:100%" src="https://www.youtube.com/embed/-cEzsCAzTak"></iframe></span></span> and that: <a href="http://example.com">http://example.com</a>',
				[],
				function ($configurator)
				{
					$configurator->Autolink;
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'http://www.youtube.com/watch?feature=player_detailpage&v=9bZkp7q19f0#t=113',
				'<span data-s9e-mediaembed="youtube" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" style="background:url(https://i.ytimg.com/vi/9bZkp7q19f0/hqdefault.jpg) 50% 50% / cover;border:0;height:100%;left:0;position:absolute;width:100%" src="https://www.youtube.com/embed/9bZkp7q19f0?start=113"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'http://www.youtube.com/watch?v=pC35x6iIPmo&list=PLOU2XLYxmsIIxJrlMIY5vYXAFcO5g83gA',
				'<span data-s9e-mediaembed="youtube" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" style="background:url(https://i.ytimg.com/vi/pC35x6iIPmo/hqdefault.jpg) 50% 50% / cover;border:0;height:100%;left:0;position:absolute;width:100%" src="https://www.youtube.com/embed/pC35x6iIPmo?list=PLOU2XLYxmsIIxJrlMIY5vYXAFcO5g83gA"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'http://www.youtube.com/watch?v=pC35x6iIPmo&list=PLOU2XLYxmsIIxJrlMIY5vYXAFcO5g83gA#t=123',
				'<span data-s9e-mediaembed="youtube" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" style="background:url(https://i.ytimg.com/vi/pC35x6iIPmo/hqdefault.jpg) 50% 50% / cover;border:0;height:100%;left:0;position:absolute;width:100%" src="https://www.youtube.com/embed/pC35x6iIPmo?list=PLOU2XLYxmsIIxJrlMIY5vYXAFcO5g83gA&amp;start=123"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'http://www.youtube.com/watch?v=wZZ7oFKsKzY&t=1h23m45s',
				'<span data-s9e-mediaembed="youtube" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" style="background:url(https://i.ytimg.com/vi/wZZ7oFKsKzY/hqdefault.jpg) 50% 50% / cover;border:0;height:100%;left:0;position:absolute;width:100%" src="https://www.youtube.com/embed/wZZ7oFKsKzY?start=5025"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'http://www.youtube.com/watch?v=wZZ7oFKsKzY&t=23m45s',
				'<span data-s9e-mediaembed="youtube" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" style="background:url(https://i.ytimg.com/vi/wZZ7oFKsKzY/hqdefault.jpg) 50% 50% / cover;border:0;height:100%;left:0;position:absolute;width:100%" src="https://www.youtube.com/embed/wZZ7oFKsKzY?start=1425"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'https://youtu.be/wI__53kBBKM?t=9h38m43s',
				'<span data-s9e-mediaembed="youtube" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" style="background:url(https://i.ytimg.com/vi/wI__53kBBKM/hqdefault.jpg) 50% 50% / cover;border:0;height:100%;left:0;position:absolute;width:100%" src="https://www.youtube.com/embed/wI__53kBBKM?start=34723"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'https://youtu.be/wI__53kBBKM?t=9h38m',
				'<span data-s9e-mediaembed="youtube" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" style="background:url(https://i.ytimg.com/vi/wI__53kBBKM/hqdefault.jpg) 50% 50% / cover;border:0;height:100%;left:0;position:absolute;width:100%" src="https://www.youtube.com/embed/wI__53kBBKM?start=34680"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'https://youtu.be/wI__53kBBKM?t=9h43s',
				'<span data-s9e-mediaembed="youtube" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" style="background:url(https://i.ytimg.com/vi/wI__53kBBKM/hqdefault.jpg) 50% 50% / cover;border:0;height:100%;left:0;position:absolute;width:100%" src="https://www.youtube.com/embed/wI__53kBBKM?start=32443"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'https://youtu.be/wI__53kBBKM?t=9h',
				'<span data-s9e-mediaembed="youtube" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" style="background:url(https://i.ytimg.com/vi/wI__53kBBKM/hqdefault.jpg) 50% 50% / cover;border:0;height:100%;left:0;position:absolute;width:100%" src="https://www.youtube.com/embed/wI__53kBBKM?start=32400"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
			[
				'https://youtu.be/wI__53kBBKM?t=38m',
				'<span data-s9e-mediaembed="youtube" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" style="background:url(https://i.ytimg.com/vi/wI__53kBBKM/hqdefault.jpg) 50% 50% / cover;border:0;height:100%;left:0;position:absolute;width:100%" src="https://www.youtube.com/embed/wI__53kBBKM?start=2280"></iframe></span></span>',
				[],
				function ($configurator)
				{
					$configurator->MediaEmbed->add('youtube');
				}
			],
		];
	}

	/**
	* @testdox Legacy rendering tests
	* @dataProvider getLegacyRenderingTests
	* @group needs-network
	*/
	public function testLegacyRendering($xml, $html, $setup = null)
	{
		$setup($this->configurator);
		$this->assertSame($html, $this->configurator->rendering->getRenderer()->render($xml));
	}

	public function getLegacyRenderingTests()
	{
		return [
			[
				'<r><BBCNEWS ad_site="/news/business" playlist="/news/business-29149086A" poster="/media/images/77590000/jpg/_77590973_mapopgetty.jpg" url="http://www.bbc.com/news/business-29149086">http://www.bbc.com/news/business-29149086</BBCNEWS></r>',
				'<span data-s9e-mediaembed="bbcnews" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" style="border:0;height:100%;left:0;position:absolute;width:100%" src="//www.bbc.com/news/business-29149086/embed"></iframe></span></span>',
				function ($configurator)
				{
					$configurator->MediaEmbed->add('bbcnews');
				}
			],
			[
				'<r><GAMETRAILERS id="mgid:arc:video:gametrailers.com:85dee3c3-60f6-4b80-8124-cf3ebd9d2a6c" url="http://www.gametrailers.com/videos/jz8rt1/tom-clancy-s-the-division-vgx-2013--world-premiere-featurette-">http://www.gametrailers.com/videos/jz8rt1/tom-clancy-s-the-division-vgx-2013--world-premiere-featurette-</GAMETRAILERS></r>',
				'<span data-s9e-mediaembed="gametrailers" style="display:inline-block;width:100%;max-width:640px"><span style="display:block;overflow:hidden;position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" style="border:0;height:100%;left:0;position:absolute;width:100%" src="//media.mtvnservices.com/embed/mgid:arc:video:gametrailers.com:85dee3c3-60f6-4b80-8124-cf3ebd9d2a6c"></iframe></span></span>',
				function ($configurator)
				{
					$configurator->MediaEmbed->add('gametrailers');
				}
			],
			[
				'<r><SOUNDCLOUD id="https://soundcloud.com/andrewbird/three-white-horses" url="https://soundcloud.com/andrewbird/three-white-horses">https://soundcloud.com/andrewbird/three-white-horses</SOUNDCLOUD></r>',
				'<iframe data-s9e-mediaembed="soundcloud" allowfullscreen="" scrolling="no" src="https://w.soundcloud.com/player/?url=https://soundcloud.com/andrewbird/three-white-horses" style="border:0;height:166px;max-width:900px;width:100%"></iframe>',
				function ($configurator)
				{
					$configurator->MediaEmbed->add('soundcloud');
				}
			],
		];
	}
}