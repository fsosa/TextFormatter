<?php

namespace s9e\TextFormatter\Tests\Configurator\Helpers;

use s9e\TextFormatter\Utils\Http;
use s9e\TextFormatter\Tests\Test;

/**
* @covers s9e\TextFormatter\Utils\Http
*/
class HttpTest extends Test
{
	/**
	* @testdox getClient() returns an instance of s9e\TextFormatter\Utils\Http\Client
	*/
	public function testGetClient()
	{
		$this->assertInstanceOf(
			's9e\\TextFormatter\\Utils\\Http\\Client',
			Http::getClient()
		);
	}
}