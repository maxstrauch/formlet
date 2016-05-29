<?php

function email_input($data) {
	echo "Validate: " . $data . "<br/>";

	return strlen($data) >= 5;
}

function lang($phrase) {
	static $lang = array(
		"Welt" => "Hallo, ein Text!",
		"msg.welcome" => "Willkommen!",
		"empty.text" => '--- Bitte auswählen ---'
	);
	return (!array_key_exists($phrase,$lang)) ? $phrase : $lang[$phrase];
}



include("formlet.php");

$f = Formlet::create('example.xml');

$f->registerDataBinding('eat', array("Apple ][", "Apple ][ c", "Apple ][ c+", "Apple ][ e", "Apple III"));

$html = $f->toHTML();

echo $html;
echo "<hr/><pre>";
echo str_replace(array("<", ">"), array("&lt;", "&gt;"), $html);
echo "</pre><hr/>";

function mulival($value, $index) {
	echo "VALIDATE: ", var_dump($value), "@", var_dump($index);
	return count($index) > 2;
}


/*

echo "<b>isSubmitted?</b>", var_dump($f->isSubmitted()), "<br/>";
echo "<b>isValid?</b>", var_dump($f->isValid()), "<br/>";
*/




/*

function email_input($data) {
	echo "VALIDATE: " . $data;

	return strlen($data) > 5;
}

function multisel($data) {
	echo "<b>VALIDATE-MULTI</b>";
	print_r($data);

	return count($data) == 1;
}


function lang($phrase) {
	static $lang = array(
		"Welt" => "Hallo",
		"msg.welcome" => "Willkommen!"
	);
	return (!array_key_exists($phrase,$lang)) ? $phrase : $lang[$phrase];
}


$fl = new Formlet;
$fl->load('test.xml');
$fl->registerDataBinding('foobar', array("Frühstück", "Mittagessen", "Abendessen"));




echo var_dump($_REQUEST), "<br>";
echo "isSubmitted? ", var_dump($fl->isSubmitted()), "<br>";
echo "isValid? ", var_dump($fl->isValid()), "<br>";

if ($fl->isValid()) {

	echo "<b>RESULT</b><br>";
	echo $fl->getValue('usn'), "<br>";
	echo $fl->getValue('pwd'), "<br>";
	echo $fl->getValue('option'), "<br>";
	echo $fl->getValue('option1'),",,,", $fl->getValue('option1', true), "<br>";

	echo var_dump( $fl->getValue('multi1') ),",,,", var_dump($fl->getValue('multi1', true)), "<br>";
	echo var_dump( $fl->getValue('multi2') ), "<br>";

	echo "<b>-----</b><br/>";


}

echo "<hr/>";

$html = $fl->toHtml();
echo $html;


echo "<hr/><hr/><pre>";
echo str_replace(array("<", ">"), array("&lt;", "&gt;"), $fl->toHtml());

*/

