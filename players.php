<?php
/***************************************************************************************************
File:			players.php
Description:	Interface to select a player and see the various functionality that can be applied
***************************************************************************************************/

include_once("functions.php");
include_once("header.php");

if ($uid <= 0)
{
	RowText("<h5>You are not authorized!</h5>");
	include_once("footer.php");
	die;
}

if (!isset($_GET['a']))
{
	data_error();
}
elseif ($_GET['a'] == 'o')
{
	// display player overview
	if (!IsNumber($_GET['id']))
		data_error();
	
	$charid = $_GET['id'];
	display_player_overview($eqdb, $charid);
}

function display_player_overview($eqdb, $charid)
{
	$query = "SELECT name FROM character_data WHERE id = {$charid}";
	$result = $eqdb->query($query);
	
	if ($result->num_rows < 1)
		data_error();
	
	$row = $result->fetch_assoc();
	$name = $row['name'];
	
	RowText("<h4>{$name}</h4>");
	Row();
		Col();
		DivC();
		Col(true, '', 8);
?>
			<table class="table">
				<thead>
					<tr>
						<th scope="col">Handins</th>
						<th scope="col">Trades</th>
						<th scope="col">Looted</th>
						<th scope="col">Dropped</th>
						<th scope="col">Deleted</th>
						<th scope="col">Inventory</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>
							<a href="handins.php?a=p&id=<?php print $charid; ?>" class="btn btn-primary btn-lg" role="button" aria-disabled="true">Handins</a>
						</td>
						<td>
							<a href="trades.php?a=p&id=<?php print $charid; ?>" class="btn btn-primary btn-lg" role="button" aria-disabled="true">Trades</a>
						</td>
						<td>
							<a href="looted.php?a=p&id=<?php print $charid; ?>" class="btn btn-primary btn-lg" role="button" aria-disabled="true">Looted</a>
						</td>
						<td>
							<a href="dropped.php?a=p&id=<?php print $charid; ?>" class="btn btn-primary btn-lg" role="button" aria-disabled="true">Dropped</a>
						</td>
						<td>
							<a href="destroyed.php?a=p&id=<?php print $charid; ?>" class="btn btn-primary btn-lg" role="button" aria-disabled="true">Destroyed</a>
						</td>
						<td>
							<a href="inventory.php?a=sr&id=<?php print $charid; ?>" class="btn btn-primary btn-lg" role="button" aria-disabled="true">Inventory</a>
						</td>
					</tr>
				</tbody>
			</table>
<?php
		DivC();
		Col();
		DivC();
	DivC();
}

include_once("footer.php");

?>