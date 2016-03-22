<?php

namespace de\toxa\txf;

require_once 'extensions/datasource/classes/transaction.php';

class transactionTest extends \PHPUnit_Framework_TestCase
{
	protected $started;
	protected $committed;
	protected $goodStarter, $badStarter, $goodCommitter, $badCommitter, $goodReverter, $badReverter;

	public function setUp()
	{
		$this->goodStarter = function() { $this->started = true; return true; };
		$this->badStarter = function() { return false; };
		$this->goodCommitter = function() { $this->started = false; $this->committed = true; return true; };
		$this->badCommitter = function() { return false; };
		$this->goodReverter = function() { $this->started = false; $this->committed = false; return true; };
		$this->badReverter = function() { return false; };

		$this->started = null;
		$this->committed = null;
	}

	public function testConstructor()
	{
		$this->assertInstanceOf( 'de\toxa\txf\datasource\transaction', new datasource\transaction( $this->goodStarter, $this->goodCommitter, $this->goodReverter ) );
		$this->assertInstanceOf( 'de\toxa\txf\datasource\transaction', new datasource\transaction( $this->badStarter, $this->goodCommitter, $this->goodReverter ) );
	}
}
