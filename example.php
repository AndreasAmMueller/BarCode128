<?php

require_once __DIR__.'/src/Barcode128.class.php';

// Text to be converted
$code = 'http://am-wd.de';

// Text printed above the barcode
$text = 'BarCode128';

// A font file located in the same directory
// http://openfontlibrary.org/en/font/hans-kendrick
$font = __DIR__."/data/HansKendrick-Regular.ttf";
// corresponding fontsize in px
$fontSize = 12;

// height of the barcode in px
$height = 130;

// create an Object of BarCode128 Class
$barcode = new AMWD\BarCode128($code, $height);

// OPTIONAL: add the font
// if not: no Text can be written (only bars)
$barcode->addFont($font, $fontSize);

// OPTIONAL: add the text above the barcode
$barcode->CustomText($text);

/*
 * with $barcode->draw() the raw image will be echoed to the stdout
 * with $barcode->save('barcode.jpg') the image will be saved as jpg
 **/

$barcode->draw();
//$barcode->save('data/barcode.gif');

?>
