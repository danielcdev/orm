<?php

class AnnotationDriver {

	public static function parseAnnotations($class) {
		$schema = array ();
		$reflect = new ReflectionClass($class);
		$classProperties = $reflect->getProperties(ReflectionProperty::IS_PRIVATE | ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PUBLIC);
		
		foreach ($classProperties as $property)
			$schema = array_merge($schema, AnnotationDriver::extractAnnotationValues($property, str_replace(str_split("/*"), "", $property->getDocComment())));
		
		return $schema;
	}

	private static function extractAnnotationValues($property, $annotations) {
		foreach (explode("\n", $annotations) as $anno) {
			$anno = trim($anno);
			
			if (empty($anno))
				continue;
			
			$values = explode(" ", $anno);
			$schema[$property->getName()][$values[0]] = $values[1];
		}
		
		return $schema;
	}
}