<?php
 
use IrfanTOOR\phpBuild;
 
class phpBuildTest extends PHPUnit_Framework_TestCase 
{
	public function testPhpBuildClassExists()
	{
		$class = new phpBuild();
	    $this->assertInstanceOf('IrfanTOOR\phpBuild', $class);
	}
}
