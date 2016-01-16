<?php
require (__DIR__ . "/exception/ormconnectexception.php");
require (__DIR__ . "/exception/ormprepareexception.php");
require (__DIR__ . "/exception/ormexecuteexception.php");
require (__DIR__ . "/exception/ormresultexception.php");
require (__DIR__ . "/restriction.php");
require (__DIR__ . "/annotationdriver.php");
require (__DIR__ . "/schemainterpreter.php");

class Orm {
	private $class;
	private $mysqli;
	private $schema = array ();
	private $restrictions = array ();

	public function save($object) {
		if (!($statement = $this->mysqli->prepare($this->insertStatement())))
			throw new OrmPrepareException($this->mysqli->error, $this->mysqli->errno);
		
		$this->assembleInsertBindData($statement, $object);
		
		if (!$statement->execute())
			throw new OrmExecuteException($this->mysqli->error, $this->mysqli->errno);
	}

	public function update($object) {
	}

	public function getList() {
		$result = $this->getResult();
		
		return $this->createInstances($result);
	}

	public function getUniqueResult() {
		$result = $this->getResult();
		
		if ($result->num_rows > 1)
			throw new OrmResultException("Query returned more than one result", 1);
		
		return $this->createInstances($result);
	}

	public function add(Restriction $restriction) {
		$this->restrictions[] = $restriction;
	}

	public function __construct($class) {
		$this->class = $class;
		$this->initiateConnection();
		
		$schemaInterpreter = new SchemaInterpreter($this->class);
		$this->schema = $schemaInterpreter->parseSchema();
	}

	private function getResult() {
		if (!($statement = $this->mysqli->prepare($this->selectStatement())))
			throw new OrmPrepareException($this->mysqli->error, $this->mysqli->errno);
		
		$this->assembleBindData($statement);
		
		if (!$statement->execute())
			throw new OrmExecuteException($this->mysqli->error, $this->mysqli->errno);
		
		if (!($result = $statement->get_result()))
			throw new OrmResultException($statement->error, $statement->errno);
		
		return $result;
	}

	private function extractInstanceData($reflect, $row, $schema = null) {
		if ($schema == null)
			$schema = &$this->schema;
		
		$object = $reflect->newInstanceWithoutConstructor();
		
		foreach ($schema as $key => $data) {
			$property = $reflect->getProperty($key);
			
			if ($property->isPrivate() || $property->isProtected())
				$property->setAccessible(true);
			
			if (isset($schema[$key]["@manytoone"])) {
				$joinedReflect = new ReflectionClass($schema[$key]["@manytoone"]);
				$joinedObject = $this->extractInstanceData($joinedReflect, $row, $schema[$key]["@schema"]);
				
				$property->setValue($object, $joinedObject);
				continue;
			}
			
			$property->setValue($object, $row[$schema[$key]["@column"]]);
		}
		
		return $object;
	}

	private function createInstances($result) {
		$reflect = new ReflectionClass($this->class);
		
		while ($row = $result->fetch_assoc()) {
			$object = $this->extractInstanceData($reflect, $row);
			$objects[] = $object;
		}
		
		return ($result->num_rows == 0) ? null : (($result->num_rows > 1) ? $objects : $object);
	}

	private function assembleBindData($statement) {
		if (empty($this->restrictions))
			return;
		
		$bindData[0] = "";
		
		foreach ($this->restrictions as $restrict) {
			$temp[] = $restrict->getValue();
			$bindData[0] .= $this->schema[$restrict->getMember()]["@type"];
			$bindData[] = &$temp[(count($temp) - 1)];
		}
		
		call_user_func_array(array (
				$statement,
				'bind_param' 
		), $bindData);
	}

	private function assembleRestrictions() {
		if (empty($this->restrictions))
			return;
		
		$statement = " WHERE ";
		
		foreach ($this->restrictions as $restrict) {
			$alias = (empty($this->schema[$restrict->getMember()]["@ijid"])) ? "a" : $this->schema[$restrict->getMember()]["@ijid"];
			$statement .= $alias . "." . $this->schema[$restrict->getMember()]["@column"] . $restrict->getStatement() . " AND ";
		}
		
		return rtrim($statement, " AND ");
	}

	private function exploreSchema($statement, $schema = null, $alias = null, $ignoreIjs = false) {
		if ($schema == null)
			$schema = &$this->schema;
		
		if ($alias == null)
			$alias = "a";
		
		$alias = $alias . ".";
		
		if ($ignoreIjs)
			$alias = null;
		
		foreach ($schema as $key => $data) {
			if (isset($schema[$key]["@manytoone"]) && !$ignoreIjs) {
				$statement["ij"] .= " INNER JOIN " . strtoupper($schema[$key]["@manytoone"]) . " " . $schema[$key]["@ijid"] . " ON " . $schema[$key]["@ijid"] . "." . $schema[$key]["@column"] . " = " . $alias . $schema[$key]["@column"];
				$statement = $this->exploreSchema($statement, $schema[$key]["@schema"], $schema[$key]["@ijid"]);
				continue;
			}
			
			$statement["s"] .= $alias . $schema[$key]["@column"] . ", ";
		}
		
		return $statement;
	}

	private function selectStatement() {
		$statement["s"] = "SELECT ";
		$statement["ij"] = "";
		$statement = $this->exploreSchema($statement);
		
		return rtrim($statement["s"], ", ") . " FROM " . strtoupper($this->class) . " a" . $statement["ij"] . $this->assembleRestrictions();
	}

	private function assembleInsertBindData($statement, $object) {
		$bindData[0] = "";
		$reflect = new ReflectionClass($this->class);
		
		foreach ($this->schema as $key => $data) {
			$property = $reflect->getProperty($key);
			
			if ($property->isPrivate() || $property->isProtected())
				$property->setAccessible(true);
			
			$temp[] = $property->getValue($object);
			$bindData[0] .= $this->schema[$key]["@type"];
			$bindData[] = &$temp[(count($temp) - 1)];
		}
		
		call_user_func_array(array (
				$statement,
				'bind_param' 
		), $bindData);
	}

	private function insertStatement() {
		$statement["s"] = "INSERT INTO " . strtoupper($this->class) . " (";
		$statement = $this->exploreSchema($statement, null, null, true);
		$statement["s"] = rtrim($statement["s"], ", ") . ") VALUES (" . str_repeat("?, ", substr_count($statement["s"], ","));
		$statement["s"] = rtrim($statement["s"], ", ") . ")";
		
		return $statement["s"];
	}

	private function initiateConnection() {
		$this->mysqli = new mysqli("127.0.0.1", "cataRFtvygBhuN", "za3wSXEdc5rvf6tBYnU", "CATA");
		
		if ($this->mysqli->connect_errno)
			throw new OrmConnectException($this->mysqli->connect_error, $this->mysqli->connect_errno);
	}
}