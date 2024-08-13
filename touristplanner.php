<?php
include "config.inc.php";

require_once 'vendor/autoload.php';
use \YaLinqo\Enumerable;

define("DEBUG",true);

if (!isset($_GET["contractState"]))
{
	$contractState = "Active";
}
else
{
	$contractState = $_GET["contractState"];
}

function debug($txt)
{
	if (DEBUG)
	{
		echo $txt."<br/>\n";
	}
}

function price($price)
{
	return floor($price/1000)."k";
}

function ksptime($int, $display = false)
{
	$adddate = $display ? 1: 0;
	
	$years = floor($int/(426*6*3600));
	$sdays = floor($int) % (426*6*3600);
	$days = floor($sdays/(6*3600));
	$shours = $sdays % (6*3600);
	$hours = floor($shours/3600);
	$sminutes = $shours % 3600;
	$minutes = floor($sminutes/60);
	$seconds = $sminutes % 60;
	
	return "Year ".($years+$adddate).", Day ".($days+$adddate)." - $hours:$minutes:$seconds";
}

$situations = Array();
$situations[] = "Suborbit";
$situations[] = "Flyby";
$situations[] = "Orbit";
$situations[] = "Flight";
$situations[] = "Land";

class Contract {
	public $guid;
	public $state;
	public $values;
	public $kerbalList;
	public $agent;
	public function expdate() {
		if ($this->state=="Active")
		{
			return $this->values[10]-$GLOBALS["timegame"];
		}
		else
		{
			return $this->values[8]-$GLOBALS["timegame"];
		}
	}
}

class Kerbal {
	public $kerbalName;
	public $price;
	public $activityList;
}

class Activity {
	public $targetBody;
	public $targetType;
	public $price;
	public $state;
	public $values;
	public $agent;
}

$bodies = json_decode(file_get_contents("bodies.json"));

?>
<!DOCTYPE html>
<html>
<head>
<title>Kerbal tourism planner</title>
<style type='text/css'>
table { text-align:center; }
.contracttitle 
{
	text-align:left;
}
.cell
{
	width: 60px;
}
td.Complete 
{
	background-color:green;
}
span.Complete
{
	text-decoration: line-through;
}
td.Incomplete
{
	background-color:orange;
}
td.dangerous
{
	border:3px solid red;
}
span.dangerous
{
	font-weight:bold;
}
.Offered
{
	background-color:grey;
}
.Active
{
	background-color:orange;
}

td:nth-child(5n+1)
{
	border-right:solid black 2px;
}

</style>
<script type='text/javascript'>
function loaded()
{
	
}
</script>
</head>
<body onload='loaded()'>
<?php
if (!defined("SAVEFILE"))
{
	echo "
	<a href='https://github.com/Goufalite/kerbalscience' target='_NEW'>Kerbal Science on Github</a><br/>
<form enctype='multipart/form-data' action='' method='post'>
  <input type='hidden' name='MAX_FILE_SIZE' value='12000000' />
  Upload your persistent.sfs save file : <input name='savefile' type='file' />
  <input type='submit' value='Submit' />
</form>";
if (count($_FILES)==0) exit();
	try {
		$f = fopen($_FILES['savefile']['tmp_name'],"r");
	}
	catch (Exception $e)
	{
		die($e);
	}
} else {
	
	try{
		if (isset($_GET["save"]) && defined("STEAMPATH"))
		{
			$f = fopen(STEAMPATH.$_GET["save"]."/persistent.sfs","r");
		}
		else
		{
			$f = fopen(SAVEFILE,"r");
		}
	}
	catch (Exception $e)
	{
		die($e);
	}
}
if (!$f)
{
	die("Bad opening file...");
}

$contracts = Array();
$incontract = false;
$inkerbal = false;
$inactivity = false;
$inscenario = false;
$timegame = 0;
while ($l = fgets($f))
{
	if (preg_match("/CONTRACT$/",trim($l)))
	{

		// full save
		if (isset($contract))
		{
			$contract->kerbalList[] = $kerbal;
			$contracts[] = $contract;
			unset($activity);
			unset($kerbal);
		}
		$inkerbal = false;
		$inactivity = false;
		$incontract = true;
		$contract = new Contract();
	}
	if (preg_match("/guid = ([a-z0-9\-]+)/",$l,$matches) && $incontract)
	{
		$contract->guid = $matches[1];
	}
	
	if (preg_match("/agent = (.*)/",trim($l),$matches) && $incontract)
	{
		$contract->agent = $matches[1];
	}
	
	// skip if not tourist contract
	if (preg_match("/type = ([A-Za-z]+)/",$l,$matches) && $incontract)
	{
		if ($matches[1]!="TourismContract")
		{
			$incontract = false;
			unset($contract);
		}
	}
	
	if (preg_match("/state = ([A-Za-z]+)/",$l, $matches) && $incontract && !$inkerbal && !$inactivity) {
		$contract->state = $matches[1];
	}
	
	// skip highg
	if (preg_match("/isGeeAdventure = True/",$l,$matches) && $incontract)
	{
		$incontract = false;
		unset($contract);
	
	}
	
	if (preg_match("/values = ([0-9,\\.]+)/",trim($l), $matches) && $incontract && !$inkerbal && !$inactivity) {
		$contract->values = preg_split("/,/",$matches[1]);
	}
	
	// KERBAL
	
	if (preg_match("/name = ([A-Za-z]+)/",$l, $matches) && $incontract && $matches[1]=="KerbalTourParameter") {
				
		if (isset($kerbal))
		{
			$contract->kerbalList[] = $kerbal;
			unset($activity);
		}
		$kerbal = new Kerbal();
		$inkerbal = true;
		$inactivity = false;
				
	}
	
	if (preg_match("/kerbalName = (.*)/",trim($l), $matches) && $incontract && $inkerbal) {
		$kerbal->kerbalName = $matches[1];
	}
	
	// ACTIVITY
	
	if (preg_match("/name = ([A-Za-z]+)/",$l, $matches) && $incontract && $inkerbal && $matches[1]=="KerbalDestinationParameter") {
		$inactivity = true;
		$activity = new Activity();
	}

	if (preg_match("/state = ([A-Za-z]+)/",$l, $matches) && $incontract && $inkerbal && $inactivity) {
		$activity->state = $matches[1];
	}
	
	if (preg_match("/values = ([0-9]+)/",$l, $matches) && $incontract && $inkerbal) {
		if ($inactivity)
		{
			$activity->price = $matches[1];
		}
		else
		{
			$kerbal->price = $matches[1];
		}
	}
	
	if (preg_match("/targetBody = ([0-9]+)/",$l, $matches) && $incontract && $inkerbal && $inactivity) {
		$activity->targetBody = $matches[1];
	}
	
	if (preg_match("/targetType = ([A-Za-z]+)/",$l, $matches) && $incontract && $inkerbal && $inactivity) {
		$activity->targetType = $matches[1];
		$kerbal->activityList[] = $activity;
		$inactivity = false;
	}
	
	if (preg_match("/CONTRACT_FINISHED/",$l))
	{
		if (isset($kerbal)) {
			$contract->kerbalList[] = $kerbal;
			$contracts[] = $contract;
		}
		break;
	}
	
	// time
	if (preg_match("/SCENARIO/",$l) && !$inscenario && $timegame==0)
	{
		$inscenario = true;
	}
	
	if (preg_match("/update = ([0-9]+)\\.[0-9]+/",$l, $matches) && $inscenario && $timegame == 0)
	{
		$timegame = intval($matches[1]);
	}
	
	
}
echo "Gametime = ".ksptime($timegame, true);
echo "<table border=1>";

// BODIES

echo "<tr><td></td>";
foreach($bodies->bodies as $i=>$v)
{
	if (isset($v->filterName) || !isset($v->id))
	{
		continue;
	}
	echo "<td colspan='";
	echo "5";
	//echo ($v->name=="Kerbin"?"4":"3");
	echo "'>".$v->name."</td>";
}
echo "</tr>";

// SITUATIONS

echo "<tr><td></td>";
foreach($bodies->bodies as $i => $v)
{
	if (isset($v->filterName) || !isset($v->id))
	{
		continue;
	}
	
	foreach($situations as $j => $s)
	{
		echo "<td class='cell'>$s</td>";
	}
}
echo "</tr>";

// FULL DISPLAY

foreach($contracts as $contract)
{
	echo "<tr><td colspan='400' class='contracttitle ".$contract->state."'>".$contract->agent." - ".$contract->guid." - ";
	echo "Exp : ".ksptime($contract->expDate());
	echo "</td>";
	echo "</tr>\n";	
	foreach ($contract->kerbalList as $kerbal)
	{
		echo "<tr><td>".$kerbal->kerbalName." (".price($kerbal->price).")</td>";
		
		foreach($bodies->bodies as $i => $v)
		{
			if (isset($v->filterName) || !isset($v->id))
			{
				continue;
			}
			
			foreach($situations as $j => $s)
			{
				echo "<td class='cell ";
				$act = @from($kerbal->activityList)->where('$v->targetBody == $GLOBALS["v"]->id && $v->targetType == $GLOBALS["s"]')->toList();
				if (count($act) != 0)
				{
					echo $act[0]->state;
					if ($s=="Flight" || $s=="Land")
					{
						echo " dangerous";
					}
					echo "'>".price($act[0]->price);
				}
				else
				{
					echo "'>";
				}
				echo "</td>";
			}
		}
		echo "</tr>\n";
	}
}




echo "</table>";

foreach($bodies->bodies as $body)
{
	if (isset($body->filterName) || !isset($body->id))
	{
		continue;
	}
	
	
	$missions = @from($contracts)->where(function($c) {
		return  from($c->kerbalList)->any(function($k) {
			return  from($k->activityList)->any(function ($a) {
				return $a->targetBody == $GLOBALS["body"]->id ;
			});
			});
		})->toList();

	if (count($missions) == 0)
	{
		continue;
	}
	echo "<hr/>";
	echo "<h1>".$body->name."</h1>\n";
	foreach($missions as $mission)
	{
		echo "<h2 class='".$mission->state."'>".$mission->guid."</h2>";
		$kerbals = from($mission->kerbalList)->where(function($k) {
			return  from($k->activityList)->any(function ($a) {
				return $a->targetBody == $GLOBALS["body"]->id ;
			});
		})->toList();
		foreach($kerbals as $kerbal)
		{
			echo "<span class='".($kerbal->price==0?"Complete":"")."'>".$kerbal->kerbalName."(".price($kerbal->price).") : </span>";
			foreach($kerbal->activityList as $activity)
			{
				if ($activity->targetBody != $body->id)
				{
					continue;
				}
				echo "<span class='".$activity->state." ";
				if ($activity->targetType=="Flight" || $activity->targetType=="Land")
				{
					echo " dangerous";
				}
				
				echo "'>".$activity->targetType."(".price($activity->price).")</span> ";
				//var_dump($activity);

			}
			echo "<br/>\n";
		}
	}
}

/*
echo "<pre>";
echo json_encode($contracts);
*/


?>
</body>
</html>