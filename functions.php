<?php

include_once("displayfunctions.php");

/*******************************************************************************
Function:	Container
Purpose:	Create a container <div>
In:			None
*******************************************************************************/
function Container()
{
	print '<div class="container">';
}

/*******************************************************************************
Function:	RowText
Purpose:	Create a row of text
In:			$text - text to display
			$center - whether or not the row is horizontally centered
			$formatting - formatting class tags
*******************************************************************************/
function RowText($text, $center = true, $formatting = "pt-4 pb-2")
{
	Row();
		Col($center, $formatting);
			print $text;
		DivC();
	DivC();
}

/*******************************************************************************
Function:	Row
Purpose:	Create a row <div>
In:			$center - whether or not the row is vertically centered
*******************************************************************************/
function Row($center = false)
{
	print '<div class="row' . ($center ? ' align-items-center' : '') . '">';
}

/*******************************************************************************
Function:	Col
Purpose:	Create a col <div>
In:			$center - whether or not text in the column is center-aligned
*******************************************************************************/
function Col($center = false, $formatting = '')
{
	print '<div class="col' . ($center ? ' text-center' : '') . ($formatting != '' ? ' ' . $formatting : '') . '">';
}

function IsNumber($id)
{
	if (!preg_match("/^\d{1,7}$/", $id, $matches))
		return false;
	return true;
}

function data_error()
{
	Row();
		Col(true, 'pt-4 pb-2');
			print "<h6>Bad Data - Script Terminated</h6>";
		DivC();
	DivC();
	
	include_once("footer.php");
	die;
}

?>