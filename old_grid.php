<?php
header ( 'Content-Type: text/html;charset=utf-8', true );
?><style>
th {
	font-size: 10px;
	font-weight: bold;
	background: #62832B;
	color: #FFFFFF;
	line-height: 15px;
	text-align: center;
}

td {
	font-size: 13px
}

.subTituloTabela {
	color: #FCF8F1;
	width: 100%;
	display: block;
	background-color: #95A874;
	line-height: 15px;
	text-align: center;
	border: 1px solid white;
}

span {
	font-size: 10px;
	padding: 0px;
}

body {
	text-align: center;
	font-family: verdana;
	color: #5A3802;
	line-height: 15px;
}

#equipes tr:nth-child(even) {
	background: #CFD4C1;
}

#equipes tr:nth-child(odd) {
	background: #EFF4E1;
}

#equipes td {
	color: 5A3802;
	padding-left: 3px
}

th:nth-child(1), td:nth-child(1) {
	width: 30px;
}

th:nth-child(2), td:nth-child(2) {
	width: 40px;
}

th:nth-child(3), td:nth-child(3) {
	width: 210px;
}

th:nth-child(4), td:nth-child(4) {
	width: 170px;
}

th:nth-child(5), td:nth-child(5) {
	width: 70px;
}
</style>

<table id="equipes" cellpadding="1" cellspacing="2" border="0">
	<thead>
		<tr>
			<th>GRID</th>
			<th>HORA</th>


			<th>EQUIPE</th>

			<th>CIDADE</th>
			<th>CAT</th>
		</tr>
	</thead>
	<tbody>
					
<?php
include 'DBCrud.php';
include 'connInfo.php';
class Grider extends MySQL_CRUD_API {
	protected function getGridInfo($grids, $categoria) {
		$gridId = $categoria;
		if ($gridId == 2) {
			$gridId = 1;
		}
		
		return $grids [$gridId];
	}
	public function executeCommand() {
		if (strcmp ( $_SERVER ['REQUEST_METHOD'], "OPTIONS" ) == 0) {
			return;
		}
		
		$db = $this->connectDatabase ( $this->configArray ["hostname"], $this->configArray ["username"], $this->configArray ["password"], $this->configArray ["database"], $this->configArray ["port"], $this->configArray ["socket"], $this->configArray ["charset"] );
		
		$gresults = $this->query ( $db, "select * from GridConfig", array () );
		$grids = array ();
		if ($gresults) {
			while ( $row = $this->fetch_assoc ( $gresults ) ) {
				
				$grids [$row ["id"]] = $row["numeracao"];
			}
		} else {
			
		}		
		$sql = "select * FROM GridFull where id_Etapa=? order by  hora,minuto";
		$params [] = ($_GET ["e"]) - 145;
		$counter = 0;
		$nomeConfig = "";
		if ($result = $this->query ( $db, $sql, $params )) {
			$colInfo = $this->getColInfo ( $result, true );
			$adjust = 0;
			while ( $row = $this->fetch_assoc ( $result ) ) {
				if (strcmp ( $nomeConfig, $row ["nome_Config"] ) != 0) {
					$nomeConfig = $row ["nome_Config"];
					echo "<tr ><td colspan=\"5\" style=\"padding-left:0px\"><span class=\"subTituloTabela\">$nomeConfig</span></td></tr>";
					$gridInfo = $this->getGridInfo ( $grids, $row ["categoria_Equipe"] );
					$adjust = $counter;
				}
				
				
				
				$hora = $row ["hora"];
				$minuto = $row ["minuto"];
				if ($minuto < 10) {
					$minuto = "0" . $minuto;
				}
				$categoria = "Pró";
				if ($row ["categoria_Equipe"] == 1) {
					$categoria = "Turismo";
				} else if ($row ["categoria_Equipe"] == 2) {
					$categoria = "Trekkers";
				} else if ($row ["categoria_Equipe"] == 3) {
					$categoria = "Graduados";
				}
				echo "<tr><td>".($counter+$gridInfo-$adjust)."</td>";
				echo "<td >$hora:$minuto</td>";
				echo "<td >" . $row ["nome_Equipe"] . "</td>";
				echo "<td>" . $row ["desc_Equipe"] . "</td>";
				echo "<td >$categoria</td>";
				echo "</tr>";
				$counter ++;
			}
			$this->close ( $result );
		} else {
			echo "nao achou ";
		}
		
		$this->close ( $result );
	}
}
$api = new Grider ( $configArray );

$api->configArray = $configArray;

$api->executeCommand ();

?>
</tbody>
</table>

