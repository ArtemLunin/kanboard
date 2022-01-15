<?php
$unauthorized = TRUE;
function connectToBase()
{
	try
	{
		$pdo = new PDO(
			'mysql:host='.HOST.';dbname='.BASE.';charset=UTF8MB4',
			USER, 
			PASSWORD, 
			[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
		);
		return $pdo;
	}
	catch (PDOException $e){
		error_log("err connect to database:".$e->getMessage());
		return FALSE;
	}
}

