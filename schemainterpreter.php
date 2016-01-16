<?php

class SchemaInterpreter {
	
	private $i = "b";
	private $class;

	public function parseSchema() {
		$schema = AnnotationDriver::parseAnnotations($this->class);
		$schema = $this->recurseSchema($schema);
		
		return $schema;
	}

	public function __construct($class) {
		$this->class = $class;
	}

	private function recurseSchema($schema) {
		foreach ($schema as $key => $data) {
			if (!isset($schema[$key]["@manytoone"]))
				continue;
			
			$schema[$key]["@schema"] = AnnotationDriver::parseAnnotations($schema[$key]["@manytoone"]);
			$schema[$key]["@ijid"] = $this->i;
			$this->i++;
			$schema[$key]["@schema"] = $this->recurseSchema($schema[$key]["@schema"]);
		}
		
		return $schema;
	}
}