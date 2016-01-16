<?php
class Restriction {
	
	private $statement;
	private $member;
	private $value;
	
	public static function eq($member, $value) {
		return new Restriction (" = ?", $member, $value );
	}
	
	public function getStatement() {
		return $this->statement;
	}
	
	public function getMember() {
		return $this->member;
	}
	
	public function getValue() {
		return $this->value;
	}
	
	private function __construct($statement, $member, $value) {
		$this->statement = $statement;
		$this->member = $member;
		$this->value = $value;
	}
}