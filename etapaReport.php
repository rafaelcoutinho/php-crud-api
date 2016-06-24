<?php
include 'DBCrud.php';
include 'connInfo.php';
class EtapaReportApi extends MySQL_CRUD_API {
	protected function removePrefix($request) {
		if (! $request)
			return false;
		
		$pos = strpos ( $request, 'relatorio/' );
		if (! $pos) {
			$pos = strpos ( $request, 'php/' );
			$pos += 4;
		} else {
			$pos += 10;
		}
		
		return $pos ? substr ( $request, $pos ) : '';
	}
	/**
	 * Formats a line (passed as a fields array) as CSV and returns the CSV as a string.
	 * Adapted from http://us3.php.net/manual/en/function.fputcsv.php#87120
	 */
	protected function arrayToCsv(array &$fields, $delimiter = ';', $enclosure = '"', $encloseAll = false, $nullToMysqlNull = false) {
		$delimiter_esc = preg_quote ( $delimiter, '/' );
		$enclosure_esc = preg_quote ( $enclosure, '/' );
		
		$output = array ();
		foreach ( $fields as $field ) {
			if ($field === null && $nullToMysqlNull) {
				$output [] = 'NULL';
				continue;
			}
			
			// Enclose fields containing $delimiter, $enclosure or whitespace
			if ($encloseAll || preg_match ( "/(?:${delimiter_esc}|${enclosure_esc}|\s)/", $field )) {
				$output [] = $enclosure . str_replace ( $enclosure, $enclosure . $enclosure, $field ) . $enclosure;
			} else {
				$output [] = $field;
			}
		}
		
		return implode ( $delimiter, $output );
	}
	public function executeCommand() {
		if (isset ( $_SERVER ['REQUEST_METHOD'] )) {
			header ( 'Access-Control-Allow-Origin: *' );
			$this->headersCommand ( NULL );
		}
		if (strcmp ( $_SERVER ['REQUEST_METHOD'], "OPTIONS" ) == 0) {
			return;
		}
		
		$request = parse_url ( $_SERVER ['REQUEST_URI'], PHP_URL_PATH );
		$pathInfo = $this->removePrefix ( $request );
		
		$paths = explode ( "/", $pathInfo );
		
		$db = $this->connectDatabase ( $this->configArray ["hostname"], $this->configArray ["username"], $this->configArray ["password"], $this->configArray ["database"], $this->configArray ["port"], $this->configArray ["socket"], $this->configArray ["charset"] );
		
		if (strcmp ( $paths [0], "Etapa" ) == 0) {
			$l = count ( $paths );
			$idEtapa = $paths [1];
			$etapa = $this->getEntityJson ( $db, "select * from Etapa where id=?", array (
					$idEtapa 
			), true );
			header ( 'Content-Type: application/csv' );
			syslog ( LOG_INFO, "etapa '" . $etapa ["titulo"] . "'" );
			$filename = str_replace ( " ", "", $etapa ["titulo"] );
			syslog ( LOG_INFO, "arquivo '" . $filename . "'" );
			header ( 'Content-disposition: attachment; filename="relatorio_Etapa'.$idEtapa.'.csv";' );
			header('Pragma: no-cache');
			$result = $this->query ( $db, "select c.nome AS nome,   c.email AS email, c.telefone AS telefone, eq.nome AS Equipe, eq.descricao AS Cidade, i.paga AS Paga  from       ((((northdb.Inscricao i      join northdb.Etapa e)     join northdb.Equipe eq)        left join northdb.Categoria cat ON ((eq.id_Categoria = cat.id)))        join northdb.Competidor c)    where        ((i.id_Etapa = e.id)            and (eq.id = i.id_Equipe)            and (c.id = i.id_Trekker)) and e.id=?", array (
					$idEtapa 
			) );
			$titles = array (
					"Nome",
					"E-mail",
					"Telefone",
					"Equipe",
					"Cidade",
					"Paga" 
			);
			$line = $this->arrayToCsv ( $titles );
			
			echo $line . "\n";
			while ( $row = $this->fetch_assoc ( $result ) ) {
				$line = $this->arrayToCsv ( $row );
				
				echo $line . "\n";
			}
		}
	}
}

$api = new EtapaReportApi ( $configArray );

$api->configArray = $configArray;

$api->executeCommand ();
?>