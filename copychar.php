<?php
/***************************************************************************************************
File:			copychar.php
Description:	Copies a character from one database/server to another. Currently only setup to 
					work from ROZ 1 to ROZ 2 due to the specific database schemas of each.
***************************************************************************************************/

include_once("functions.php");
include_once("header.php");

// Check for permissions
if (!$permission['copychar'])
{
	RowText("<h5>You are not authorized!</h5>");
	include_once("footer.php");
	die;
}

RowText("<h4>Copy Character</h4>");


if (!isset($_GET['a']))
{
	// No action set - select origin connection - Step 1
	display_select_origin_connection($admindb, $uid);
}
elseif ($_GET['a'] == "nn")
{
	// New Name for character copy
	if (!IsNumber($_POST['id']) || !IsNumber($_POST['origin']) || !IsNumber($_POST['destination']))
		data_error();
	
	if (!IsText($_POST['characterName']))
		data_error();
	
	// connect to destination db	
	$destinationdb = DatabaseConnection($admindb, $_POST['destination'], $uid);
	if (!$destinationdb)
		data_error();
	
	// See if new name is available
	$query = "SELECT id FROM character_data WHERE name = '{$_POST['characterName']}'";
	$result = $destinationdb->query($query);
	if (!$result)
		data_error();
	
	if ($result->num_rows == 1)
	{
		// Name not available
		RowText("Name not available. Please try another.");
		
		Row();
			Col();
			DivC();
			Col(true, '', 4);
			// display form to select another name
?>
				<form action="copychar.php?a=nn" method="post">
					<div class="form-group">
						<!--<label for="characterName">Character Name</label>!-->
						<input type="text" class="form-control" id="characterName" placeholder="Enter New Character Name" name="characterName">
					</div>
					<input type="hidden" name="origin" value="<?php print $_GET['origin']; ?>">
					<input type="hidden" name="destination" value="<?php print $_GET['destination']; ?>">
					<input type="hidden" name="id" value="<?php print $_GET['id']; ?>">
					<button type="submit" class="btn btn-primary">Check Name</button>
				</form>
<?php
			DivC();
			Col();
			DivC();
		DivC();
		include_once("footer.php");
		die;
	}
	// multiple characters of same name, something is wrong
	elseif ($result->num_rows > 1)
		data_error();
	// nothing found, name available
	else
		RowText("Name {$_POST['characterName']} available on destination server.");
	
	// connect to origin db
	$origindb = DatabaseConnection($admindb, $_POST['origin'], $uid);
	if (!$origindb)
		data_error();
	
	// find the account number of the proper account on the destination server
	
	// first get the account number and name from the origin server
	$query = "SELECT character_data.account_id AS account_id, account.name AS account_name FROM character_data LEFT JOIN account ON character_data.account_id = account.id WHERE character_data.id = {$_POST['id']}";
	$result = $origindb->query($query);
	if (!$result)
		data_error();
	$row = $result->fetch_assoc();
	$oldaccountid = $row['account_id'];
	$newaccountid = 0;
	$account_name = $row['account_name'];
	
	// search by name for account on destination server
	$query = "SELECT id FROM account WHERE name = '{$account_name}'";
	$result = $destinationdb->query($query);
	if (!$result)
		data_error();
	
	if ($result->num_rows == 0)
	{
		// account does not exist on destination server
		RowText("Account does not exist on destination server. Have player login to account if you would like to copy to this account, and try again.");
	}
	elseif ($result->num_rows == 1)
	{
		// Account exists - check for open slot.
		$row = $result->fetch_assoc();
		$newaccountid = $row['id'];
		$query = "SELECT count(*) AS numchars FROM character_data WHERE account_id = {$newaccountid} AND deleted_at IS NULL";
		$accountresult = $destinationdb->query($query);
		if (!$accountresult)
			data_error();
		if ($accountresult->num_rows < 12)
		{
			// less than 12 characters (ROF2 client max)
			RowText("Space available on account on destination server.");
			RowText("");
			Row();
				Col();
				DivC();
				Col(true, '', 4);
?>
					<form action="copychar.php?a=c" method="post">
						<input type="hidden" name="origin" value="<?php print $_POST['origin']; ?>">
						<input type="hidden" name="destination" value="<?php print $_POST['destination']; ?>">
						<input type="hidden" name="id" value="<?php print $_POST['id']; ?>">
						<input type="hidden" name="sa" value="1">
						<input type="hidden" name="sn" value="0">
						<input type="hidden" name="characterName" value="<?php print $_POST['characterName']; ?>">
						<button type="submit" class="btn btn-primary">Copy to Same Account</button>
					</form>
<?php
				DivC();
				Col();
				DivC();
			DivC();
			RowText("or");
		}
		
	}
	// multiple accounts of that name - something is wrong
	elseif ($result->num_rows > 1)
		data_error();
		
	Row();
		Col();
		DivC();
		Col(true, '', 4);
			// Check Account context so can copy to different account
?>
			<form action="copychar.php?a=ca" method="post">
				<div class="form-group">
					<!--<label for="accountName">Account Name</label>!-->
					<input type="text" class="form-control" id="accountName" placeholder="Enter Account Name" name="accountName">
				</div>
				<input type="hidden" name="origin" value="<?php print $_POST['origin']; ?>">
				<input type="hidden" name="destination" value="<?php print $_POST['destination']; ?>">
				<input type="hidden" name="id" value="<?php print $_POST['id']; ?>">
				<input type="hidden" name="sa" value="0">
				<input type="hidden" name="sn" value="0">
				<input type="hidden" name="characterName" value="<?php print $_POST['characterName']; ?>">
				<button type="submit" class="btn btn-primary">Copy to Different Account</button>
			</form>
<?php
		DivC();
		Col();
		DivC();
	DivC();
}
// Process Copy
elseif ($_GET['a'] == "p")
{
	// dump everything in case there's an issue
	var_dump($_POST);
	
	if (!IsNumber($_POST['sn']) || !IsNumber($_POST['sa']) || !IsNumber($_POST['id']) || !IsNumber($_POST['origin']) || !IsNumber($_POST['destination']))
		data_error();
	
	if (isset($_POST['accountName']) && !IsTextAndNumbers($_POST['accountName']))
	{
		data_error();
	}
	
	if (isset($_POST['characterName']) && !IsText($_POST['characterName']))
		data_error();
	
	RowText("Processing Copy");
	
	copy_character($_POST['origin'], $_POST['destination'], $admindb, $uid, $_POST['sn'], $_POST['sa'], $_POST['id'], ($_POST['sn'] ? "" : $_POST['characterName']), ($_POST['sa'] ? "" : $_POST['accountName']));
}
// Check account
elseif ($_GET['a'] == "ca")
{
	RowText("Checking Account");
	
	if (!IsNumber($_POST['sn']) || !IsNumber($_POST['sa']) || !IsNumber($_POST['id']) || !IsNumber($_POST['origin']) || !IsNumber($_POST['destination']))
		data_error();
	
	if (!IsTextAndNumbers($_POST['accountName']))
		data_error();
	
	if (isset($_POST['characterName']) && !IsText($_POST['characterName']))
	{
		data_error();
	}
	
	$account_name = $_POST['accountName'];
	
	$destinationdb = DatabaseConnection($admindb, $_POST['destination'], $uid);
	if (!$destinationdb)
		data_error();
	
	// Check for account on destination server, and get ID if exists
	$query = "SELECT id FROM account WHERE name = '{$account_name}'";
	$result = $destinationdb->query($query);
	if ($result->num_rows == 0)
	{
		RowText("Destination account does not exist. Please create it or try another.");
		Row();
			Col();
			DivC();
			Col(true, '', 4);
				// Check Account context
?>
				<form action="copychar.php?a=ca" method="post">
					<div class="form-group">
						<!--<label for="accountName">Account Name</label>!-->
						<input type="text" class="form-control" id="accountName" placeholder="Enter Account Name" name="accountName">
					</div>
					<input type="hidden" name="origin" value="<?php print $_POST['origin']; ?>">
					<input type="hidden" name="destination" value="<?php print $_POST['destination']; ?>">
					<input type="hidden" name="id" value="<?php print $_POST['id']; ?>">
					<input type="hidden" name="sa" value="0">
					<input type="hidden" name="sn" value="<?php print $_POST['sn']; ?>">
<?php
					if (isset($_POST['characterName']))
						print "<input type='hidden' name='characterName' value='{$_POST['characterName']}'>";
					if (isset($_POST['accountName']))
						print "<input type='hidden' name='accountName' value='{$_POST['accountName']}'>";
?>
					<button type="submit" class="btn btn-primary">Copy to Different Account</button>
				</form>
<?php
			DivC();
			Col();
			DivC();
		DivC();
		
		// terminate script
		include_once("footer.php");
		die;
	}
	elseif ($result->num_rows > 1)
		data_error();
		
	$row = $result->fetch_assoc();
	$account_id = $row['id'];
	
	// Check for open slot
	$query = "SELECT count(*) AS count FROM character_data WHERE account_id = {$account_id} AND deleted_at IS NULL";
	$result = $destinationdb->query($query);
	if ($result->num_rows == 0)
		data_error();
	$row = $result->fetch_assoc();
	
	if ($row['count'] < 12)
	{
		// There's space - confirm before processing
		RowText("Destination account has space.");
		
		$origindb = DatabaseConnection($admindb, $_POST['origin'], $uid);
		if (!$origindb)
			data_error();
		
		// Get Player Name
		$query = "SELECT name FROM character_data WHERE id = {$_POST['id']}";
		$result = $origindb->query($query);
		if ($result->num_rows != 1)
			data_error();
		$row = $result->fetch_assoc();
		$player_name = $row['name'];
		
		// Get Origin Server Name
		$query = "SELECT name, user FROM connections WHERE id = {$_POST['origin']}";
		$result = $admindb->query($query);
		if ($result->num_rows != 1)
			data_error();
		$row = $result->fetch_assoc();
		$origin_name = $row['name'];
		
		// Get Destination Server Name
		$query = "SELECT name, user FROM connections WHERE id = {$_POST['destination']}";
		$result = $admindb->query($query);
		if ($result->num_rows != 1)
			data_error();
		$row = $result->fetch_assoc();
		$destination_name = $row['name'];
		
		if ($_POST['sn'])		
			RowText("Copy character {$player_name} from {$origin_name} to {$destination_name} keeping the same name and new account {$account_name}?");
		else
			RowText("Copy character {$player_name} from {$origin_name} to {$destination_name} using new name {$_POST['characterName']} and new account {$account_name}?");
		
		RowText("");
		Row();
			Col();
			DivC();
			Col(true, '', 4);
?>
				<form action="copychar.php?a=p" method="post">
					<input type="hidden" name="origin" value="<?php print $_POST['origin']; ?>">
					<input type="hidden" name="destination" value="<?php print $_POST['destination']; ?>">
					<input type="hidden" name="id" value="<?php print $_POST['id']; ?>">
					<input type="hidden" name="accountName" value="<?php print $account_name; ?>">
					<input type="hidden" name="sa" value="<?php print $_POST['sa']; ?>">
					<input type="hidden" name="sn" value="<?php print $_POST['sn']; ?>">
<?php
					if (isset($_POST['characterName']))
						print "<input type='hidden' name='characterName' value='{$_POST['characterName']}'>";
?>
					<button type="submit" class="btn btn-primary">PROCESS COPY</button>
				</form>
<?php
			DivC();
			Col();
			DivC();
		DivC();
	}
	else
	{
		RowText("Account is full. Please free a character slot or try another account.");
		Row();
			Col();
			DivC();
			Col(true, '', 4);
				// Check Account context
?>
				<form action="copychar.php?a=ca" method="post">
					<div class="form-group">
						<!--<label for="accountName">Account Name</label>!-->
						<input type="text" class="form-control" id="accountName" placeholder="Enter Account Name" name="accountName">
					</div>
					<input type="hidden" name="origin" value="<?php print $_POST['origin']; ?>">
					<input type="hidden" name="destination" value="<?php print $_POST['destination']; ?>">
					<input type="hidden" name="id" value="<?php print $_POST['id']; ?>">
					<input type="hidden" name="sa" value="<?php print $_POST['sa']; ?>">
					<input type="hidden" name="sn" value="<?php print $_POST['sn']; ?>">
<?php
					if (isset($_POST['characterName']))
						print "<input type='hidden' name='characterName' value='{$_POST['characterName']}'>";
?>
					<button type="submit" class="btn btn-primary">Copy to Different Account</button>
				</form>
<?php
			DivC();
			Col();
			DivC();
		DivC();
	}
	
}
// Confirm
elseif ($_GET['a'] == "c")
{
	if (!IsNumber($_POST['sn']) || !IsNumber($_POST['sa']) || !IsNumber($_POST['id']) || !IsNumber($_POST['origin']) || !IsNumber($_POST['destination']))
		data_error();
	
	if (isset($_POST['characterName']) && !IsText($_POST['characterName']))
		data_error();
	
	if (isset($_POST['accountName']) && !IsTextAndNumbers($_POST['accountName']))
		data_error();
	
	$origindb = DatabaseConnection($admindb, $_POST['origin'], $uid);
	
	// Get Player Name
	$query = "SELECT name FROM character_data WHERE id = {$_POST['id']}";
	$result = $origindb->query($query);
	if ($result->num_rows != 1)
		data_error();
	$row = $result->fetch_assoc();
	$oldplayername = $row['name'];
	
	// Get Origin Server Name
	$query = "SELECT name, user FROM connections WHERE id = {$_POST['origin']}";
	$result = $admindb->query($query);
	if ($result->num_rows != 1)
		data_error();
	$row = $result->fetch_assoc();
	$originname = $row['name'];
	
	// Get Destination Server Name
	$query = "SELECT name, user FROM connections WHERE id = {$_POST['destination']}";
	$result = $admindb->query($query);
	if ($result->num_rows != 1)
		data_error();
	$row = $result->fetch_assoc();
	$destinationname = $row['name'];
	
	$myconfirmation = "Copy character {$oldplayername} from {$originname} to {$destinationname} " . ($_POST['sn'] ? "keeping the same name " : "using new name {$_POST['characterName']} ") . ($_POST['sa'] ? "and keeping the same account?" : "using new account {$_POST['accountName']}?");
	RowText($myconfirmation);
	
	RowText("");
	Row();
		Col();
		DivC();
		Col(true, '', 4);
?>
			<form action="copychar.php?a=p" method="post">
				<input type="hidden" name="origin" value="<?php print $_POST['origin']; ?>">
				<input type="hidden" name="destination" value="<?php print $_POST['destination']; ?>">
				<input type="hidden" name="id" value="<?php print $_POST['id']; ?>">
				<input type="hidden" name="sa" value="<?php print $_POST['sa']; ?>">
				<input type="hidden" name="sn" value="<?php print $_POST['sn']; ?>">
<?php
					if (isset($_POST['characterName']))
						print "<input type='hidden' name='characterName' value='{$_POST['characterName']}'>";
?>					
				<button type="submit" class="btn btn-primary">PROCESS COPY</button>
			</form>
<?php
		DivC();
		Col();
		DivC();
	DivC();
}
// Check Name
elseif ($_GET['a'] == "cn")
{
	if (!IsNumber($_GET['id']) || !IsNumber($_GET['o']) || !IsNumber($_GET['d']))
		data_error();
	
	$origindb = DatabaseConnection($admindb, $_GET['o'], $uid);
	if (!$origindb)
		data_error();
	
	$destinationdb = DatabaseConnection($admindb, $_GET['d'], $uid);
	if (!$destinationdb)
		data_error();
	
	$query = "SELECT character_data.name AS char_name, character_data.account_id AS account_id, account.name AS account_name FROM character_data LEFT JOIN account ON character_data.account_id = account.id WHERE character_data.id = {$_GET['id']}";
	$result = $origindb->query($query);
	if ($result->num_rows != 1)
		data_error();
	$row = $result->fetch_assoc();
	$playername = $row['char_name'];
	$oldaccountid = $row['account_id'];
	$newaccountid = 0;
	$account_name = $row['account_name'];
	
	$query = "SELECT id, account_id FROM character_data WHERE name = '{$playername}'";
	$result = $destinationdb->query($query);
	if ($result->num_rows == 0)
	{
		// No results - name available
		RowText("Name <b>{$playername}</b> available on destination server. Checking for space on the account.");
		
		// Get account id based on account name on destination server
		$query = "SELECT id FROM account WHERE name = '{$account_name}'";
		$resultaccount = $destinationdb->query($query);
		if ($resultaccount->num_rows != 1)
		{
			RowText("Account <b>{$account_name}</b> does not exist on destination server.");
		}
		else
		{
			$row = $resultaccount->fetch_assoc();
			$newaccountid = $row['id'];
			$query = "SELECT count(*) AS numchars FROM character_data WHERE account_id = {$newaccountid} AND deleted_at IS NULL";
			$resultaccount = $destinationdb->query($query);
			if ($resultaccount->num_rows == 0)
				data_error();
			$row = $resultaccount->fetch_assoc();
			if ($row['numchars'] < 12)
			{
				RowText("Space available on account on destination server.");
				RowText("");
				Row();
					Col();
					DivC();
					Col(true, '', 4);
?>
						<form action="copychar.php?a=c" method="post">
							<input type="hidden" name="origin" value="<?php print $_GET['o']; ?>">
							<input type="hidden" name="destination" value="<?php print $_GET['d']; ?>">
							<input type="hidden" name="id" value="<?php print $_GET['id']; ?>">
							<input type="hidden" name="sa" value="1">
							<input type="hidden" name="sn" value="1">
							<button type="submit" class="btn btn-primary">Copy to Same Account</button>
						</form>
<?php
					DivC();
					Col();
					DivC();
				DivC();
				RowText("or");
			}
			else
				RowText("Destination Account is full.");
		}
		
		Row();
			Col();
			DivC();
			Col(true, '', 4);
				// Check Account context
?>
				<form action="copychar.php?a=ca" method="post">
					<div class="form-group">
						<!--<label for="accountName">Account Name</label>!-->
						<input type="text" class="form-control" id="accountName" placeholder="Enter Account Name" name="accountName">
					</div>
					<input type="hidden" name="origin" value="<?php print $_GET['o']; ?>">
					<input type="hidden" name="destination" value="<?php print $_GET['d']; ?>">
					<input type="hidden" name="id" value="<?php print $_GET['id']; ?>">
					<input type="hidden" name="sa" value="0">
					<input type="hidden" name="sn" value="1">
					<button type="submit" class="btn btn-primary">Copy to Different Account</button>
				</form>
<?php
			DivC();
			Col();
			DivC();
		DivC();
	}

	elseif ($result->num_rows == 1)
	{
		// Name is taken - prompt for new
		RowText("Name <b>{$playername}</b> is taken on destination server.");
		RowText("Choose a new name, or rename/delete existing character on destination server and try again.");

		Row();
			Col();
			DivC();
			Col(true, '', 4);
?>
				<form action="copychar.php?a=nn" method="post">
					<div class="form-group">
						<!--<label for="characterName">Character Name</label>!-->
						<input type="text" class="form-control" id="characterName" placeholder="Enter New Character Name" name="characterName">
					</div>
					<input type="hidden" name="origin" value="<?php print $_GET['o']; ?>">
					<input type="hidden" name="destination" value="<?php print $_GET['d']; ?>">
					<input type="hidden" name="id" value="<?php print $_GET['id']; ?>">
					<button type="submit" class="btn btn-primary">Check Name</button>
				</form>
<?php
			DivC();
			Col();
			DivC();
		DivC();

	}
	else	// Multiple characters of same name? Error
		data_error();
}
// Search characters
elseif ($_GET['a'] == "s")
{
	if (!IsNumber($_POST['origin']) || !IsNumber($_POST['destination']))
		data_error();
	$origin = $_POST['origin'];
	$destination = $_POST['destination'];
	
	display_char_search($origin, $destination);
}
// Select Destination Server
elseif ($_GET['a'] == "sd")
{
	if (!IsNumber($_POST['origin']))
		data_error();
	
	$origin = $_POST['origin'];
	display_select_destination_connection($admindb, $uid, $origin);
}
// Player search results
elseif ($_GET['a'] == "sp")
{
	if (!IsText($_POST['playerName']))
		data_error();
	
	if (!IsNumber($_POST['origin']) || !IsNumber($_POST['destination']))
		data_error();
	$origin = $_POST['origin'];
	$destination = $_POST['destination'];
	
	$playername = $eqdb->real_escape_string($_POST['playerName']);
	
	$origindb = DatabaseConnection($admindb, $origin, $uid);
	if (!$origindb)
		data_error();
	
	display_char_search_results($origindb, $playername, $origin, $destination);
}
// Select Origin Connection - Step 1
else
{
	display_select_origin_connection($admindb, $uid);
}

include_once("footer.php");

function copy_character($odb, $ddb, $adb, $uid, $same_name, $same_account, $character_id, $new_character_name = "", $new_account_name = "")
{
	RowText($same_name);
	RowText($same_account);
	RowText($character_id);
	RowText($new_character_name);
	RowText($new_account_name);
	
	$process_on = true;
	$origindb = DatabaseConnection($adb, $odb, $uid);
	$destinationdb = DatabaseConnection($adb, $ddb, $uid);
	
	// Find next char ID
	$query = "SELECT max(id) AS mymax FROM character_data";
	$result = $destinationdb->query($query);
	if (!$result)
		data_error();
	if ($result->num_rows != 1)
		data_error();
	$row = $result->fetch_assoc();
	$new_id = $row['mymax'] + 1;
	
	// account stuff
	
	if ($same_account)
	{
		$query = "SELECT character_data.account_id AS account_id, account.name AS account_name FROM character_data LEFT JOIN account ON character_data.account_id = account.id WHERE character_data.id = {$character_id}";
		$result = $origindb->query($query);
		if (!$result || $result->num_rows != 1)
			data_error();
		$row = $result->fetch_assoc();
		$new_account_name = $row['account_name'];
	}
	
	$new_account_id = 0;
	
	$query = "SELECT id FROM account WHERE name = '{$new_account_name}'";
	$result = $destinationdb->query($query);
	if (!$result || $result->num_rows != 1)
		data_error();
	$row = $result->fetch_assoc();
	$new_account_id = $row['id'];
	
	// character_data table first
	$query = "SELECT * FROM character_data WHERE id = {$character_id}";
	$result = $origindb->query($query);
	$row = $result->fetch_assoc();
	
	$query = "INSERT INTO character_data (";
	
	foreach ($row as $key => $value)
	{
		$query = $query . "`" . $key . "`, ";
	}
	
	/* Disabled is_online column now
	if (!isset($row['is_online']))
		$query = $query . "`is_online`, ";
	*/
	
	$query = rtrim($query, " ");
	$query = rtrim($query, ",");
	$query = $query . ") VALUES (";
	
	foreach ($row as $key => $value)
	{
		if ($key == "id")
			$query = $query . $new_id . ', ';
		elseif ($key == "account_id")
			$query = $query . $new_account_id . ', ';
		elseif ($value == "")
			$query = $query . "'', ";
		elseif ($key == "name")
		{
			if ($same_name)
				$new_character_name = $value;
			$query = $query . "'" . $new_character_name . "', ";
		}
		elseif ($key == "last_name" || $key == "title" || $key == "suffix" || $key == "mailkey")
			$query = $query . "'" . $value . "', ";
		else
			$query =  $query . $value . ', ';
	}
	
	/* Disabled is_online column now
	if (!isset($row['is_online']))
		$query = $query . "0, ";
	*/

	$query = rtrim($query, " ");
	
	$query = rtrim($query, ",");
	
	$query = $query . ")";
	
	RowText($query);
	
	if ($process_on)
		$result = $destinationdb->query($query);
	
	RowText("New ID: {$new_id}");
	
	// character_alternate_abilities
	
	$query = "SELECT * FROM character_alternate_abilities WHERE id = {$character_id}";
	$result = $origindb->query($query);
	
	if ($result->num_rows < 1)
		RowText("No Alternate Abilities");
	else
	{
		$query = "INSERT INTO character_alternate_abilities VALUES ";
		while ($row = $result->fetch_assoc())
		{
			$query = $query . "(";
			foreach ($row as $key => $value)
			{
				if ($key == "id")
					$query = $query . $new_id . ",";
				else
				{
					if ($value == "")
						$query = $query . "NULL, ";
					else
						$query =  $query . $value . ',';
				}
			}
			$query = rtrim($query, ',');
			$query = $query . "),";
		}
		
		$query = rtrim($query, ",");
		
		if ($process_on)
		{
			$result = $destinationdb->query($query);
			if (!$result)
				RowText("character_alternate_abilities insert failed");
		}
	}
	
	RowText($query);
	
	// character_bind
	$query = "SELECT * FROM character_bind WHERE id = {$character_id}";
	$result = $origindb->query($query);
	
	if ($result->num_rows < 1)
		RowText("No Binds?");
	else
	{
		$query = "INSERT INTO character_bind VALUES ";
		while ($row = $result->fetch_assoc())
		{
			$query = $query . "(";
			foreach ($row as $key => $value)
			{
				if ($key == "id")
					$query = $query . $new_id . ",";
				else
				{
					if ($value == "")
						$query = $query . "NULL, ";
					else
						$query =  $query . $value . ',';
				}
			}
			$query = rtrim($query, ',');
			$query = $query . "),";
		}
		
		$query = rtrim($query, ",");
		if ($process_on)
		{
			$result = $destinationdb->query($query);
			if (!$result)
				RowText("character_bind insert failed");
		}
	}
	RowText($query);

	// character_currency
	$query = "SELECT * FROM character_currency WHERE id = {$character_id}";
	$result = $origindb->query($query);
	
	if ($result->num_rows < 1)
		RowText("No currency?");
	else
	{
		$query = "INSERT INTO character_currency VALUES ";
		while ($row = $result->fetch_assoc())
		{
			$query = $query . "(";
			foreach ($row as $key => $value)
			{
				if ($key == "id")
					$query = $query . $new_id . ",";
				else
				{
					if ($value == "")
						$query = $query . "NULL, ";
					else
						$query =  $query . $value . ',';
				}
			}
			$query = rtrim($query, ',');
			$query = $query . "),";
		}
		
		$query = rtrim($query, ",");
		if ($process_on)
		{
			$result = $destinationdb->query($query);
			if (!$result)
				RowText("character_currency insert failed");
		}
	}
	RowText($query);
	
	// character_disciplines
	$query = "SELECT * FROM character_disciplines WHERE id = {$character_id}";
	$result = $origindb->query($query);
	
	if ($result->num_rows < 1)
		RowText("No disciplines");
	else
	{
		$query = "INSERT INTO character_disciplines VALUES ";
		while ($row = $result->fetch_assoc())
		{
			$query = $query . "(";
			foreach ($row as $key => $value)
			{
				if ($key == "id")
					$query = $query . $new_id . ",";
				else
				{
					if ($value == "")
						$query = $query . "NULL, ";
					else
						$query =  $query . $value . ',';
				}
			}
			$query = rtrim($query, ',');
			$query = $query . "),";
		}
		
		$query = rtrim($query, ",");
		if ($process_on)
		{
			$result = $destinationdb->query($query);
			if (!$result)
				RowText("character_disciplines insert failed");
		}
	}
	RowText($query);
	
	// character_inspect_messages
	$query = "SELECT * FROM character_inspect_messages WHERE id = {$character_id}";
	$result = $origindb->query($query);
	
	if ($result->num_rows < 1)
		RowText("No inspect message?");
	else
	{
		$query = "INSERT INTO character_inspect_messages VALUES ";
		if (!$result || $result->num_rows != 1)
			data_error();
		
		$row = $result->fetch_assoc();
		$query = $query . "(";
		foreach ($row as $key => $value)
		{
			if ($key == "id")
				$query = $query . $new_id . ",";
			else
			{
				if ($value == "")
					$query = $query . "'',";
				else
					$query =  $query . "'" . $destinationdb->real_escape_string($value) . "',";
			}
		}
		
		$query = rtrim($query, ",");
		$query = $query . ")";
		if ($process_on)
		{
			$result = $destinationdb->query($query);
			if (!$result)
				RowText("character_inspect_messages insert failed");
		}
	}
	RowText($query);
	
	// character_languages
	$query = "SELECT * FROM character_languages WHERE id = {$character_id}";
	$result = $origindb->query($query);
	
	if ($result->num_rows < 1)
		RowText("No languages?");
	else
	{
		$query = "INSERT INTO character_languages VALUES ";
		while ($row = $result->fetch_assoc())
		{
			$query = $query . "(";
			foreach ($row as $key => $value)
			{
				if ($key == "id")
					$query = $query . $new_id . ",";
				else
				{
					if ($value == "")
						$query = $query . "NULL, ";
					else
						$query =  $query . $value . ',';
				}
			}
			$query = rtrim($query, ',');
			$query = $query . "),";
		}
		
		$query = rtrim($query, ",");
		if ($process_on)
		{
			$result = $destinationdb->query($query);
			if (!$result)
				RowText("character_languages insert failed");
		}
	}
	RowText($query);
	
	// character_material
	$query = "SELECT * FROM character_material WHERE id = {$character_id}";
	$result = $origindb->query($query);
	
	if ($result->num_rows < 1)
		RowText("No material");
	else
	{
		$query = "INSERT INTO character_material VALUES ";
		while ($row = $result->fetch_assoc())
		{
			$query = $query . "(";
			foreach ($row as $key => $value)
			{
				if ($key == "id")
					$query = $query . $new_id . ",";
				else
				{
					if ($value == "")
						$query = $query . "NULL, ";
					else
						$query =  $query . $value . ',';
				}
			}
			$query = rtrim($query, ',');
			$query = $query . "),";
		}
		
		$query = rtrim($query, ",");
		if ($process_on)
		{
			$result = $destinationdb->query($query);
			if (!$result)
				RowText("character_material insert failed");
		}
	}
	RowText($query);
	
	// character_memmed_spells
	$query = "SELECT * FROM character_memmed_spells WHERE id = {$character_id}";
	$result = $origindb->query($query);
	
	if ($result->num_rows < 1)
		RowText("No memmed_spells");
	else
	{
		$query = "INSERT INTO character_memmed_spells VALUES ";
		while ($row = $result->fetch_assoc())
		{
			$query = $query . "(";
			foreach ($row as $key => $value)
			{
				if ($key == "id")
					$query = $query . $new_id . ",";
				else
				{
					if ($value == "")
						$query = $query . "NULL, ";
					else
						$query =  $query . $value . ',';
				}
			}
			$query = rtrim($query, ',');
			$query = $query . "),";
		}
		
		$query = rtrim($query, ",");
		if ($process_on)
		{
			$result = $destinationdb->query($query);
			if (!$result)
				RowText("character_memmed_spells insert failed");
		}
	}
	RowText($query);
	
	// character_pvp
	$query = "SELECT * FROM character_pvp WHERE char_id = {$character_id}";
	$result = $origindb->query($query);
	
	if ($result->num_rows < 1)
		RowText("No character_pvp");
	else
	{
		$query = "INSERT INTO character_pvp VALUES ";
		while ($row = $result->fetch_assoc())
		{
			$query = $query . "(";
			foreach ($row as $key => $value)
			{
				if ($key == "char_id")
					$query = $query . $new_id . ",";
				else
				{
					if ($value == "")
						$query = $query . "NULL, ";
					else
						$query =  $query . $value . ',';
				}
			}
			$query = rtrim($query, ',');
			$query = $query . "),";
		}
		
		$query = rtrim($query, ",");
		if ($process_on)
		{
			$result = $destinationdb->query($query);
			if (!$result)
				RowText("character_pvp insert failed");
		}
	}
	RowText($query);
	
	// character_skills
	$query = "SELECT * FROM character_skills WHERE id = {$character_id}";
	$result = $origindb->query($query);
	
	if ($result->num_rows < 1)
		RowText("No character_skills");
	else
	{
		$query = "INSERT INTO character_skills VALUES ";
		while ($row = $result->fetch_assoc())
		{
			$query = $query . "(";
			foreach ($row as $key => $value)
			{
				if ($key == "id" || $key == "char_id")
					$query = $query . $new_id . ",";
				else
				{
					if ($value == "")
						$query = $query . "NULL, ";
					else
						$query =  $query . $value . ',';
				}
			}
			$query = rtrim($query, ',');
			$query = $query . "),";
		}
		
		$query = rtrim($query, ",");
		if ($process_on)
		{
			$result = $destinationdb->query($query);
			if (!$result)
				RowText("character_skills insert failed");
		}
	}
	RowText($query);
	
	// character_spells
	$query = "SELECT * FROM character_spells WHERE id = {$character_id}";
	$result = $origindb->query($query);
	
	if ($result->num_rows < 1)
		RowText("No character_spells");
	else
	{
		$query = "INSERT INTO character_spells VALUES ";
		while ($row = $result->fetch_assoc())
		{
			$query = $query . "(";
			foreach ($row as $key => $value)
			{
				if ($key == "id" || $key == "char_id")
					$query = $query . $new_id . ",";
				else
				{
					if ($value == "")
						$query = $query . "NULL, ";
					else
						$query =  $query . $value . ',';
				}
			}
			$query = rtrim($query, ',');
			$query = $query . "),";
		}
		
		$query = rtrim($query, ",");
		if ($process_on)
		{
			$result = $destinationdb->query($query);
			if (!$result)
				RowText("character_spells insert failed");
		}
	}
	RowText($query);
	
	// faction_values
	$query = "SELECT * FROM faction_values WHERE char_id = {$character_id}";
	$result = $origindb->query($query);
	
	if ($result->num_rows < 1)
		RowText("No faction_values");
	else
	{
		$query = "INSERT INTO faction_values VALUES ";
		while ($row = $result->fetch_assoc())
		{
			$query = $query . "(";
			foreach ($row as $key => $value)
			{
				if ($key == "id" || $key == "char_id")
					$query = $query . $new_id . ",";
				else
				{
					if ($value == "")
						$query = $query . "'', ";
					else
						$query =  $query . $value . ',';
				}
			}
			$query = rtrim($query, ',');
			$query = $query . "),";
		}
		
		$query = rtrim($query, ",");
		if ($process_on)
		{
			$result = $destinationdb->query($query);
			if (!$result)
				RowText("faction_values insert failed");
		}
	}
	RowText($query);
	
	// inventory
	$query = "SELECT * FROM inventory WHERE charid = {$character_id}";
	$result = $origindb->query($query);
	
	if ($result->num_rows < 1)
		RowText("No faction_values");
	else
	{
		$query = "INSERT INTO inventory VALUES ";
		while ($row = $result->fetch_assoc())
		{
			$query = $query . "(";
			foreach ($row as $key => $value)
			{
				if ($key == "charid")
					$query = $query . $new_id . ",";
				else
				{
					if ($value == "")
						$query = $query . "'',";
					else
						$query =  $query . $value . ',';
				}
			}
			$query = rtrim($query, ',');
			$query = $query . "),";
		}
		
		$query = rtrim($query, ",");
		if ($process_on)
		{
			$result = $destinationdb->query($query);
			if (!$result)
				RowText("inventory insert failed");
		}
	}
	RowText($query);
}

function display_select_destination_connection($admindb, $uid, $origin)
{
	RowText("");
	Row();
		Col();
		DivC();
		Col(true, '', 4);
?>
			<form action="copychar.php?a=s" method="post">
				<div class="form-group">
					<label for="destination"><h6>Select Destination Server</h6></label>
					<select class="form-control" id="destination" name="destination">
<?php
						$query = "SELECT id, name FROM connections WHERE user = {$uid} AND id <> {$origin}";
						$result = $admindb->query($query);
				
						while ($row = $result->fetch_assoc())
						{
							print "<option value='{$row['id']}'>{$row['name']}</option>";
						}
?>
					</select>
				</div>
				<input type="hidden" name="origin" value="<?php print $origin; ?>">
				<button type="submit" class="btn btn-primary">Next</button>
			</form>
<?php
		DivC();
		Col();
		DivC();
	DivC();
}

function display_select_origin_connection($admindb, $uid)
{
	RowText("");
	Row();
		Col();
		DivC();
		Col(true, '', 4);
?>
			<form action="copychar.php?a=sd" method="post">
				<div class="form-group">
					<label for="origin"><h6>Select Origin Server</h6></label>
					<select class="form-control" id="origin" name="origin">
<?php
						$query = "SELECT id, name FROM connections WHERE user = {$uid}";
						$result = $admindb->query($query);
				
						while ($row = $result->fetch_assoc())
						{
							print "<option value='{$row['id']}'>{$row['name']}</option>";
						}
?>
					</select>
				</div>
				<button type="submit" class="btn btn-primary">Next</button>
			</form>
<?php
		DivC();
		Col();
		DivC();
	DivC();
}

function display_char_search_results($origindb, $playername, $origin, $destination)
{
	$query = "SELECT character_data.id AS id, character_data.name AS charname, character_data.level AS level, guild_members.guild_id, guilds.name AS gname FROM character_data LEFT JOIN guild_members ON character_data.id = guild_members.char_id LEFT JOIN guilds ON guild_members.guild_id = guilds.id WHERE character_data.name LIKE '%{$playername}%'";
	$result = $origindb->query($query);
	
	if($result->num_rows < 1)
	{
		RowText("<h5>No Players Found</h5>");
		display_char_search($origin, $destination);
		include_once("footer.php");
		die;
	}
	Row();
		Col();
		DivC();
		Col(true, '', 6);
?>
			<table class="table">
				<thead>
					<tr>
						<th scope="col">Character</th>
						<th scope="col">Guild</th>
						<th scope="col">Level</th>
					</tr>
				</thead>
				<tbody>
<?php
					while ($row = $result->fetch_assoc())
					{
						print "<tr><td>";
						Hyperlink("copychar.php?a=cn&id={$row['id']}&o={$origin}&d={$destination}", $row['charname']);
						print "</td><td>{$row['gname']}</td><td>{$row['level']}</td></tr>";
					}
?>
				</tbody>
			</table>
<?php
		DivC();
		Col();
		DivC();
	DivC();
}

function display_char_search($origin, $destination)
{
	Row();
		Col();
		DivC();
		Col(false, '', 6);
?>
			<form action="copychar.php?a=sp" method="post">
				<div class="form-group">
					<label for="playerName">Player Name</label>
					<input type="text" class="form-control" id="playerName" placeholder="Enter Player Name" name="playerName">
				</div>
				<input type="hidden" name="origin" value="<?php print $origin; ?>">
				<input type="hidden" name="destination" value="<?php print $destination; ?>">
				<button type="submit" class="btn btn-primary">Submit</button>
			</form>
<?php
		DivC();
		Col();
		DivC();
	DivC();
}

?>