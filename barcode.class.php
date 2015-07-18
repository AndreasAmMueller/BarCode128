<?php

/**
 * barcode.class.php
 *
 * @author Andreas Mueller <webmaster@am-wd.de>
 * @version 1.2-20140904
 *
 * @description
 * With this class you can produce Barcodes
 * in Code128 style (full ASCII)
 **/
namespace AMWD;

@error_reporting(0);

/* ---                     DEPENDENCIES                           ---
------------------------------------------------------------------ */
function_exists('imagecreatetruecolor') or die('GD Library needed');


class BarCode128 {
	// Class Attributes
	private $set    = array();
	private $vals   = array();
	private $curSet = '';

	private $checksum = array(
	'value' => 0,
	'data'  => array()
	);

	private $encoding = array(
	'value'   => '',
	'data'    => array(),
	'strings' => array()
	);

	private $code = array(
	'value' => '',
	'data'  => array()
	);
	private $text;

	private $dims = array(
	'width'       => 0,
	'height'      => 0,
	'b_width'     => 0,
	'b_spacing'   => 0,
	'px_width'    => 0,
	'txt_spacing' => 0
	);

	private $bboxCode;
	private $bboxText;

	private $font;
	private $fontSize;
	private $calcFontSize;

	private $flags = array(
	'fontResize' => false,
	'showCode'   => false
	);

	private $image;
	private $white, $black;


	// constructor
	public function __construct($code, $height = 150, $font = null, $fontSize = 10) {
		// set code for bar-parsing
		$this->setCodecSet();
		$this->setCode($code);

		// default settings
		$this->setBorderWidth(2);
		$this->setBorderSpacing(10);
		$this->setLineWidth(1);
		$this->setTextSpacing(5);
		$this->setCustomText('');
		$this->setShowCode(true);
		$this->setFontResize(false);

		// add font, if given
		if ($font == null) {
			$this->font = null;
		} else {
			$this->addFont($font, $fontSize);
		}

		// calc width and height
		$this->setHeight($height - ($this->getBorderWidth() + $this->getBorderSpacing()) * 2);
		$this->setWidth($this->calcWidth());
	}


	/* --- IMAGE AND DRAWS ---
	----------------------- */
	private function allocateColors() {
		$this->white = imagecolorallocate($this->image, 0xff, 0xff, 0xff);
		$this->black = imagecolorallocate($this->image, 0x00, 0x00, 0x00);
	}

	public function get($type = 'png') {
		$this->setWidth($this->calcWidth());

		$this->image = imagecreatetruecolor($this->getWidth(), $this->getHeight());
		$this->allocateColors();
		imagefill($this->image, 0, 0, $this->white);

		if ($this->getBorderWidth() > 0) {
			$this->drawBorder();
		}

		if (!empty($this->text)) {
			$this->drawText();
		}

		$this->drawBarCode();

		if ($this->getShowCode()) {
			$this->drawCode();
		}

		ob_start();

		switch ($type) {
		case 'gif':
			imagegif($this->image);
			break;
		case 'jpg':
		case 'jpeg':
			imagejpeg($this->image);
			break;
		default:
			imagepng($this->image);
		}

		$img = ob_get_clean();
		imagedestroy($this->image);

		return $img;
	}

	public function draw($type = 'png') {
		switch ($type) {
		case 'gif':
			header('Content-Type: image/gif');
			break;
		case 'jpg':
		case 'jpeg':
			header('Content-Type: image/jpeg');
			break;
		default:
			header('Content-Type: image/png');
		}

		echo $this->get($type);
	}

	public function save($file = 'barcode.png') {
		$tmp = explode('.', $file);
		$type = $tmp[(count($tmp)-1)];

		$this->setWidth($this->calcWidth());

		$this->image = imagecreatetruecolor($this->getWidth(), $this->getHeight());
		$this->allocateColors();
		imagefill($this->image, 0, 0, $this->white);

		if ($this->getBorderWidth() > 0) {
			$this->drawBorder();
		}

		if (!empty($this->text)) {
			$this->drawText();
		}

		$this->drawBarCode();

		if ($this->getShowCode()) {
			$this->drawCode();
		}

		switch ($type) {
		case 'gif':
			imagegif($this->image, $file);
			break;
		case 'jpg':
		case 'jpeg':
			imagejpeg($this->image, $file);
			break;
		default:
			imagepng($this->image, $file);
		}
		imagedestroy($this->image);
	}


	private function drawBorder() {
		$w = $this->getWidth();
		$h = $this->getHeight();
		imagesetthickness($this->image, 1);
		for ($i = 0; $i < $this->getBorderWidth(); $i++) {
			$x1 = $i; $y1 = $i;
			$x2 = $w-$i-1; $y2 = $h-$i-1;
			imageline($this->image, $x1, $y1, ($x2-1), $y1, $this->black);
			imageline($this->image, $x1, ($y1+1), $x1, $y2, $this->black);
			imageline($this->image, ($x1+1), $y2, $x2, $y2, $this->black);
			imageline($this->image, $x2, $y1, $x2, ($y2-1), $this->black);
		}
	}

	private function drawBarCode() {
		$str = $this->encoding['value'];
		$x = $this->getBorderWidth() + $this->getBorderSpacing();

		for ($i = 0; $i < strlen($str); $i++) {
			if ($str{$i} == 1) {
				$col = $this->black;
			} else {
				$col = $this->white;
			}

			for ($j = 0; $j < $this->getLineWidth(); $j++) {
				// start
				if (!empty($this->text)) {
					$y1 = $this->getBorderWidth() + $this->getBorderSpacing() + $this->getTextSpacing() + $this->fontSize;
				} else {
					$y1 = $this->getBorderWidth() + $this->getBorderSpacing();
				}
				// end
				if ($this->getShowCode()) {
					$y2 = ($this->getHeight() - ($this->getBorderWidth() + $this->getBorderSpacing() + $this->getTextSpacing())) - $this->bboxCode['height'];
				} else {
					$y2 = $this->getHeight() - ($this->getBorderWidth() + $this->getBorderSpacing());
				}

				imagesetthickness($this->image, 1);
				imageline($this->image, $x, $y1, $x, $y2, $col);
				$x++;
			}
		}
	}

	private function drawCode() {
		if ($this->font == null)
				return;

		$x = (($this->getWidth() - $this->bboxCode['width']) / 2) - abs($this->bboxCode['x']);
		$y = $this->getHeight() - abs($this->bboxCode[1]) - $this->getBorderWidth() - $this->getBorderSpacing();

		imagettftext($this->image, $this->getFontSize(), 0, $x, $y, $this->black, $this->getFont(), $this->getCode());
	}

	private function drawText() {
		if ($this->font == null)
				return;

		$x = (($this->getWidth() -$this->bboxText['width']) / 2) - abs($this->bboxText['x']);
		$y = abs($this->bboxText[1]) + $this->getBorderWidth() + $this->getBorderSpacing() + $this->getTextSpacing();

		imagettftext($this->image, $this->fontSize, 0, $x, $y, $this->black, $this->getFont(), $this->text);
	}


	/* --- SIMPLE GETTER AND SETTER ---
	-------------------------------- */
	public function setCode($code) {
		$this->code['value'] = $code;
		$this->generateData();
	}

	public function getCode() {
		return $this->code['value'];
	}

	public function setShowCode($val) {
		if ($val != true && $val != false)
				throw new Exception('show text can only be true or false');

		$this->flags['showCode'] = $val;
	}

	public function getShowCode() {
		return $this->flags['showCode'];
	}

	public function setCustomText($val) {
		$this->text = trim($val);
	}

	public function getCustomText() {
		return $this->text;
	}

	public function setFontResize($val) {
		if ($val != true && $val != false)
				throw new Exception('font resize can only be true or false');

		$this->flags['fontResize'] = $val;
	}

	public function getFontResize() {
		return $this->flags['fontResize'];
	}

	public function setLineWidth($px) {
		if ($px < 1)
				throw new Exception('line width less or equal zero');

		$this->dims['px_width'] = $px;
		$this->setWidth($this->calcWidth());
	}

	public function getLineWidth() {
		return $this->dims['px_width'];
	}

	public function setBorderSpacing($px) {
		if ($px < 0)
				throw new Exception('border spacing less than zero');

		$this->dims['b_spacing'] = $px;
		$this->setWidth($this->calcWidth());
	}

	public function getBorderSpacing() {
		return $this->dims['b_spacing'];
	}

	public function setBorderWidth($px) {
		if ($px < 0)
				throw new Exception('border width less than zero');

		$this->dims['b_width'] = $px;
		$this->setWidth($this->calcWidth());
	}

	public function getBorderWidth() {
		return $this->dims['b_width'];
	}

	public function setTextSpacing($px) {
		if ($px < 0)
				throw new Exception('text spacing less than zero');

		$this->dims['txt_spacing'] = $px;
	}

	public function getTextSpacing() {
		return $this->dims['txt_spacing'];
	}

	public function addFont($font, $fontSize) {
		$this->setFont($font);
		$this->initFontSize($fontSize);
	}

	private function setFont($font) {
		if (!file_exists($font))
				throw new Exception('font does not exists');

		$this->font = $font;
	}

	private function initFontSize($size) {
		if ($size < 1)
				throw new Exception('font size less or equal zero');

		$this->fontSize = $size;
		$this->calcFontSize = $size;
	}

	public function getFont() {
		return $this->font;
	}

	private function setFontSize($px) {
		$this->calcFontSize = $px;
	}

	public function getFontSize() {
		return $this->calcFontSize;
	}


	/* --- IMAGE DIMENSIONS ---
	------------------------ */
	private function setHeight($px) {
		$this->dims['height'] = $px;
	}

	public function getHeight() {
		return $this->dims['height'];
	}

	private function setWidth($px) {
		$this->dims['width'] = $px;
		if ($this->font != false) {
			if ($this->getFontResize()) {
				$width = $this->getWidth() - ($this->getBorderWidth() + $this->getBorderSpacing() + $this->getTextSpacing() * 2);
				$this->setFontSize($this->calcFontSize($width));
			} else {
				$this->bboxCode = $this->calcTTFBBox($this->getFontSize(), $this->getFont(), $this->getCode());
			}
			$this->bboxText = $this->calcTTFBBox($this->fontSize, $this->getFont(), $this->text);
		}
	}

	public function getWidth() {
		return $this->dims['width'];
	}


	/* --- CALCULATIONS ---
	-------------------- */
	private function calcWidth() {
		$len = strlen($this->encoding['value']);
		$px  = $this->getLineWidth();
		$b   = $this->getBorderWidth();
		$sp  = $this->getBorderSpacing();

		return ($len * $px + ($b + $sp) * 2);
	}

	private function calcFontSize($width) {
		$i = 1; $run = true;
		while ($run) {
			$bbox = $this->calcTTFBBox($i, $this->getFont(), $this->getCode());
			if ($bboxCode['width'] < $width) {
				$i++;
			} else {
				$i--;
				$bbox = $this->calcTTFBBox($i, $this->getFont(), $this->getCode());
				$run = false;
			}
		}
		$this->bboxCode = $bbox;
		return $i;
	}

	private function calcTTFBBox($fontSize, $font, $text) {
		$bbox = imagettfbbox($fontSize, 0, $font, $text);
		if($bbox[0] >= -1) {
			$bbox['x'] = abs($bbox[0] + 1) * -1;
		} else {
			$bbox['x'] = abs($bbox[0] + 2);
		}
		$bbox['width'] = abs($bbox[2] - $bbox[0]);
		if($bbox[0] < -1) {
			$bbox['width'] = abs($bbox[2]) + abs($bbox[0]) - 1;
		}
		$bbox['y'] = abs($bbox[5] + 1);
		$bbox['height'] = abs($bbox[7]) - abs($bbox[1]);
		if($bbox[3] > 0) {
			$bbox['height'] = abs($bbox[7] - $bbox[1]) - 1;
		}
		return $bbox;
	}


	/* --- DATA FOR ENCODING ---
	------------------------- */
	private function generateData() {
		$code = $this->getCode();
		$data = array();
		for ($i = 0; $i < strlen($code); $i++) {
			if ($i == strlen($code)-1) {
				// last char
				$val = $code{$i};
				$data[] = $this->replaceSpecialChars($val);
			} else {
				if (is_numeric($code{$i}) && is_numeric($code{$i+1})) {
					$data[] = $code{$i}.$code{$i+1};
					$i++;
				} else {
					$val = $code{$i};
					$data[] = $this->replaceSpecialChars($val);
				}
			}
		}
		$this->code['data'] = $data;

		if (isset($data[0]))
				$this->setStartSet($this->getCharSet($data[0]));

		for ($i = 0; $i < count($data); ++$i) {
			$set = $this->getCharSet($data[$i]);
			if ($set != $this->curSet) {
				$this->changeSet($set);
			}

			$val = $this->getCharVal($set, $data[$i]);
			$this->addChecksum($val);
			$this->addEncoding($val);
		}

		$chk = $this->checksum['value'];
		$this->addEncoding($chk % 103);
		$this->addEncoding(106);
	}

	private function replaceSpecialChars($val) {
		switch ($val) {
			case ' ': return 'SP';
			case "\t": return 'HT';
			case "\f": return 'FF';
			default: return $val;
		}
	}

	private function getCharVal($set, $char) {
		return $this->set[$set][$char];
	}

	private function changeSet($set) {
		$this->curSet = $set;
		switch ($set) {
		case 'A':
			$this->addChecksum('101');
			$this->addEncoding('101');
			break;
		case 'B':
			$this->addChecksum('100');
			$this->addEncoding('100');
			break;
		case 'C':
			$this->addChecksum('99');
			$this->addEncoding('99');
			break;
		}
	}

	private function getCharSet($char) {
		$set = $this->set;
		$curSet = $this->curSet;
		$sets = array('A', 'B', 'C');

		if (!empty($curSet)) {
			if (array_key_exists($char, $set[$curSet])) {
				return $curSet;
			}
			$index = array_search($curSet, $sets);
			unset($sets[$index]);
			sort($sets);
		}

		for ($i = 0; $i < count($sets); ++$i) {
			if (array_key_exists($char, $set[$sets[$i]])) {
				return $sets[$i];
			}
		}
	}

	private function setStartSet($set) {
		$this->curSet = $set;
		switch ($set) {
		case 'A':
			$this->addChecksum('103');
			$this->addEncoding('103');
			break;
		case 'B':
			$this->addChecksum('104');
			$this->addEncoding('104');
			break;
		case 'C':
			$this->addChecksum('105');
			$this->addEncoding('105');
			break;
		}
	}

	private function addChecksum($val) {
		$chk = $this->checksum;
		$count = count($chk['data']);

		if ($count == 0) {
			$chk['data'][] = $val;
		} else {
			$chk['data'][] = ($val * $count);
		}
		$chk['value'] = array_sum($chk['data']);

		$this->checksum = $chk;
	}

	private function addEncoding($val) {
		$enc = $this->encoding;

		$enc['data'][] = $val;
		$enc['value'] .= $this->getVal($val);
		$enc['strings'][] = $this->getVal($val);

		$this->encoding = $enc;
	}


	public function getVal($val) {
		return $this->vals[$val];
	}

	private function setCodecSet() {
		$set = array(); $vals = array();

		$set['A']['SP']     = '0';
		$set['A']['!']      = '1';
		$set['A']['"']      = '2';
		$set['A']['#']      = '3';
		$set['A']['$']      = '4';
		$set['A']['%']      = '5';
		$set['A']['&']      = '6';
		$set['A']["'"]      = '7';
		$set['A']['(']      = '8';
		$set['A'][')']      = '9';
		$set['A']['*']      = '10';
		$set['A']['+']      = '11';
		$set['A'][',']      = '12';
		$set['A']['-']      = '13';
		$set['A']['.']      = '14';
		$set['A']['/']      = '15';
		$set['A']['0']      = '16';
		$set['A']['1']      = '17';
		$set['A']['2']      = '18';
		$set['A']['3']      = '19';
		$set['A']['4']      = '20';
		$set['A']['5']      = '21';
		$set['A']['6']      = '22';
		$set['A']['7']      = '23';
		$set['A']['8']      = '24';
		$set['A']['9']      = '25';
		$set['A'][':']      = '26';
		$set['A'][';']      = '27';
		$set['A']['<']      = '28';
		$set['A']['=']      = '29';
		$set['A']['>']      = '30';
		$set['A']['?']      = '31';
		$set['A']['@']      = '32';
		$set['A']['A']      = '33';
		$set['A']['B']      = '34';
		$set['A']['C']      = '35';
		$set['A']['D']      = '36';
		$set['A']['E']      = '37';
		$set['A']['F']      = '38';
		$set['A']['G']      = '39';
		$set['A']['H']      = '40';
		$set['A']['I']      = '41';
		$set['A']['J']      = '42';
		$set['A']['K']      = '43';
		$set['A']['L']      = '44';
		$set['A']['M']      = '45';
		$set['A']['N']      = '46';
		$set['A']['O']      = '47';
		$set['A']['P']      = '48';
		$set['A']['Q']      = '49';
		$set['A']['R']      = '50';
		$set['A']['S']      = '51';
		$set['A']['T']      = '52';
		$set['A']['U']      = '53';
		$set['A']['V']      = '54';
		$set['A']['W']      = '55';
		$set['A']['X']      = '56';
		$set['A']['Y']      = '57';
		$set['A']['Z']      = '58';
		$set['A']['[']      = '59';
		$set['A']["\\"]     = '60';
		$set['A'][']']      = '61';
		$set['A']['^']      = '62';
		$set['A']['_']      = '63';
		$set['A']['NUL']    = '64';
		$set['A']['SOH']    = '65';
		$set['A']['STX']    = '66';
		$set['A']['ETX']    = '67';
		$set['A']['EOT']    = '68';
		$set['A']['ENQ']    = '69';
		$set['A']['ACK']    = '70';
		$set['A']['BEL']    = '71';
		$set['A']['BS']     = '72';
		$set['A']['HT']     = '73';
		$set['A']['LF']     = '74';
		$set['A']['VT']     = '75';
		$set['A']['FF']     = '76';
		$set['A']['CR']     = '77';
		$set['A']['SO']     = '78';
		$set['A']['SI']     = '79';
		$set['A']['DLE']    = '80';
		$set['A']['DC1']    = '81';
		$set['A']['DC2']    = '82';
		$set['A']['DC3']    = '83';
		$set['A']['DC4']    = '84';
		$set['A']['NAK']    = '85';
		$set['A']['SYN']    = '86';
		$set['A']['ETB']    = '87';
		$set['A']['CAN']    = '88';
		$set['A']['EM']     = '89';
		$set['A']['SUB']    = '90';
		$set['A']['ESC']    = '91';
		$set['A']['FS']     = '92';
		$set['A']['GS']     = '93';
		$set['A']['RS']     = '94';
		$set['A']['US']     = '95';
		$set['A']['FNC3']   = '96';
		$set['A']['FNC2']   = '97';
		$set['A']['SHIFT']  = '98';
		$set['A']['CodeC']  = '99';
		$set['A']['CodeB']  = '100';
		$set['A']['FNC4']   = '101';
		$set['A']['FNC1']   = '102';
		$set['A']['STARTA'] = '103';
		$set['A']['STARTB'] = '104';
		$set['A']['STARTC'] = '105';
		$set['A']['STOP']   = '106';

		$set['B']['SP']     = '0';
		$set['B']['!']      = '1';
		$set['B']['"']      = '2';
		$set['B']['#']      = '3';
		$set['B']['$']      = '4';
		$set['B']['%']      = '5';
		$set['B']['&']      = '6';
		$set['B']["'"]      = '7';
		$set['B']['(']      = '8';
		$set['B'][')']      = '9';
		$set['B']['*']      = '10';
		$set['B']['+']      = '11';
		$set['B'][',']      = '12';
		$set['B']['-']      = '13';
		$set['B']['.']      = '14';
		$set['B']['/']      = '15';
		$set['B']['0']      = '16';
		$set['B']['1']      = '17';
		$set['B']['2']      = '18';
		$set['B']['3']      = '19';
		$set['B']['4']      = '20';
		$set['B']['5']      = '21';
		$set['B']['6']      = '22';
		$set['B']['7']      = '23';
		$set['B']['8']      = '24';
		$set['B']['9']      = '25';
		$set['B'][':']      = '26';
		$set['B'][';']      = '27';
		$set['B']['<']      = '28';
		$set['B']['=']      = '29';
		$set['B']['>']      = '30';
		$set['B']['?']      = '31';
		$set['B']['@']      = '32';
		$set['B']['A']      = '33';
		$set['B']['B']      = '34';
		$set['B']['C']      = '35';
		$set['B']['D']      = '36';
		$set['B']['E']      = '37';
		$set['B']['F']      = '38';
		$set['B']['G']      = '39';
		$set['B']['H']      = '40';
		$set['B']['I']      = '41';
		$set['B']['J']      = '42';
		$set['B']['K']      = '43';
		$set['B']['L']      = '44';
		$set['B']['M']      = '45';
		$set['B']['N']      = '46';
		$set['B']['O']      = '47';
		$set['B']['P']      = '48';
		$set['B']['Q']      = '49';
		$set['B']['R']      = '50';
		$set['B']['S']      = '51';
		$set['B']['T']      = '52';
		$set['B']['U']      = '53';
		$set['B']['V']      = '54';
		$set['B']['W']      = '55';
		$set['B']['X']      = '56';
		$set['B']['Y']      = '57';
		$set['B']['Z']      = '58';
		$set['B']['[']      = '59';
		$set['B']["\\"]     = '60';
		$set['B'][']']      = '61';
		$set['B']['^']      = '62';
		$set['B']['_']      = '63';
		$set['B']['`']      = '64';
		$set['B']['a']      = '65';
		$set['B']['b']      = '66';
		$set['B']['c']      = '67';
		$set['B']['d']      = '68';
		$set['B']['e']      = '69';
		$set['B']['f']      = '70';
		$set['B']['g']      = '71';
		$set['B']['h']      = '72';
		$set['B']['i']      = '73';
		$set['B']['j']      = '74';
		$set['B']['k']      = '75';
		$set['B']['l']      = '76';
		$set['B']['m']      = '77';
		$set['B']['n']      = '78';
		$set['B']['o']      = '79';
		$set['B']['p']      = '80';
		$set['B']['q']      = '81';
		$set['B']['r']      = '82';
		$set['B']['s']      = '83';
		$set['B']['t']      = '84';
		$set['B']['u']      = '85';
		$set['B']['v']      = '86';
		$set['B']['w']      = '87';
		$set['B']['x']      = '88';
		$set['B']['y']      = '89';
		$set['B']['z']      = '90';
		$set['B']['{']      = '91';
		$set['B']['|']      = '92';
		$set['B']['}']      = '93';
		$set['B']['~']      = '94';
		$set['B']['DEL']    = '95';
		$set['B']['FNC3']   = '96';
		$set['B']['FNC2']   = '97';
		$set['B']['SHIFT']  = '98';
		$set['B']['CodeC']  = '99';
		$set['B']['FNC4']   = '100';
		$set['B']['CodeA']  = '101';
		$set['B']['FNC1']   = '102';
		$set['B']['STARTA'] = '103';
		$set['B']['STARTB'] = '104';
		$set['B']['STARTC'] = '105';
		$set['B']['STOP']   = '106';

		$set['C']['00']     = '0';
		$set['C']['01']     = '1';
		$set['C']['02']     = '2';
		$set['C']['03']     = '3';
		$set['C']['04']     = '4';
		$set['C']['05']     = '5';
		$set['C']['06']     = '6';
		$set['C']['07']     = '7';
		$set['C']['08']     = '8';
		$set['C']['09']     = '9';
		$set['C']['10']     = '10';
		$set['C']['11']     = '11';
		$set['C']['12']     = '12';
		$set['C']['13']     = '13';
		$set['C']['14']     = '14';
		$set['C']['15']     = '15';
		$set['C']['16']     = '16';
		$set['C']['17']     = '17';
		$set['C']['18']     = '18';
		$set['C']['19']     = '19';
		$set['C']['20']     = '20';
		$set['C']['21']     = '21';
		$set['C']['22']     = '22';
		$set['C']['23']     = '23';
		$set['C']['24']     = '24';
		$set['C']['25']     = '25';
		$set['C']['26']     = '26';
		$set['C']['27']     = '27';
		$set['C']['28']     = '28';
		$set['C']['29']     = '29';
		$set['C']['30']     = '30';
		$set['C']['31']     = '31';
		$set['C']['32']     = '32';
		$set['C']['33']     = '33';
		$set['C']['34']     = '34';
		$set['C']['35']     = '35';
		$set['C']['36']     = '36';
		$set['C']['37']     = '37';
		$set['C']['38']     = '38';
		$set['C']['39']     = '39';
		$set['C']['40']     = '40';
		$set['C']['41']     = '41';
		$set['C']['42']     = '42';
		$set['C']['43']     = '43';
		$set['C']['44']     = '44';
		$set['C']['45']     = '45';
		$set['C']['46']     = '46';
		$set['C']['47']     = '47';
		$set['C']['48']     = '48';
		$set['C']['49']     = '49';
		$set['C']['50']     = '50';
		$set['C']['51']     = '51';
		$set['C']['52']     = '52';
		$set['C']['53']     = '53';
		$set['C']['54']     = '54';
		$set['C']['55']     = '55';
		$set['C']['56']     = '56';
		$set['C']['57']     = '57';
		$set['C']['58']     = '58';
		$set['C']['59']     = '59';
		$set['C']['60']     = '60';
		$set['C']['61']     = '61';
		$set['C']['62']     = '62';
		$set['C']['63']     = '63';
		$set['C']['64']     = '64';
		$set['C']['65']     = '65';
		$set['C']['66']     = '66';
		$set['C']['67']     = '67';
		$set['C']['68']     = '68';
		$set['C']['69']     = '69';
		$set['C']['70']     = '70';
		$set['C']['71']     = '71';
		$set['C']['72']     = '72';
		$set['C']['73']     = '73';
		$set['C']['74']     = '74';
		$set['C']['75']     = '75';
		$set['C']['76']     = '76';
		$set['C']['77']     = '77';
		$set['C']['78']     = '78';
		$set['C']['79']     = '79';
		$set['C']['80']     = '80';
		$set['C']['81']     = '81';
		$set['C']['82']     = '82';
		$set['C']['83']     = '83';
		$set['C']['84']     = '84';
		$set['C']['85']     = '85';
		$set['C']['86']     = '86';
		$set['C']['87']     = '87';
		$set['C']['88']     = '88';
		$set['C']['89']     = '89';
		$set['C']['90']     = '90';
		$set['C']['91']     = '91';
		$set['C']['92']     = '92';
		$set['C']['93']     = '93';
		$set['C']['94']     = '94';
		$set['C']['95']     = '95';
		$set['C']['96']     = '96';
		$set['C']['97']     = '97';
		$set['C']['98']     = '98';
		$set['C']['99']     = '99';
		$set['C']['CodeB']  = '100';
		$set['C']['CodeA']  = '101';
		$set['C']['FNC1']   = '102';
		$set['C']['STARTA'] = '103';
		$set['C']['STARTB'] = '104';
		$set['C']['STARTC'] = '105';
		$set['C']['STOP']   = '106';

		$vals['0']   = '11011001100';
		$vals['1']   = '11001101100';
		$vals['2']   = '11001100110';
		$vals['3']   = '10010011000';
		$vals['4']   = '10010001100';
		$vals['5']   = '10001001100';
		$vals['6']   = '10011001000';
		$vals['7']   = '10011000100';
		$vals['8']   = '10001100100';
		$vals['9']   = '11001001000';
		$vals['10']  = '11001000100';
		$vals['11']  = '11000100100';
		$vals['12']  = '10110011100';
		$vals['13']  = '10011011100';
		$vals['14']  = '10011001110';
		$vals['15']  = '10111001100';
		$vals['16']  = '10011101100';
		$vals['17']  = '10011100110';
		$vals['18']  = '11001110010';
		$vals['19']  = '11001011100';
		$vals['20']  = '11001001110';
		$vals['21']  = '11011100100';
		$vals['22']  = '11001110100';
		$vals['23']  = '11101101110';
		$vals['24']  = '11101001100';
		$vals['25']  = '11100101100';
		$vals['26']  = '11100100110';
		$vals['27']  = '11101100100';
		$vals['28']  = '11100110100';
		$vals['29']  = '11100110010';
		$vals['30']  = '11011011000';
		$vals['31']  = '11011000110';
		$vals['32']  = '11000110110';
		$vals['33']  = '10100011000';
		$vals['34']  = '10001011000';
		$vals['35']  = '10001000110';
		$vals['36']  = '10110001000';
		$vals['37']  = '10001101000';
		$vals['38']  = '10001100010';
		$vals['39']  = '11010001000';
		$vals['40']  = '11000101000';
		$vals['41']  = '11000100010';
		$vals['42']  = '10110111000';
		$vals['43']  = '10110001110';
		$vals['44']  = '10001101110';
		$vals['45']  = '10111011000';
		$vals['46']  = '10111000110';
		$vals['47']  = '10001110110';
		$vals['48']  = '11101110110';
		$vals['49']  = '11010001110';
		$vals['50']  = '11000101110';
		$vals['51']  = '11011101000';
		$vals['52']  = '11011100010';
		$vals['53']  = '11011101110';
		$vals['54']  = '11101011000';
		$vals['55']  = '11101000110';
		$vals['56']  = '11100010110';
		$vals['57']  = '11101101000';
		$vals['58']  = '11101100010';
		$vals['59']  = '11100011010';
		$vals['60']  = '11101111010';
		$vals['61']  = '11001000010';
		$vals['62']  = '11110001010';
		$vals['63']  = '10100110000';
		$vals['64']  = '10100001100';
		$vals['65']  = '10010110000';
		$vals['66']  = '10010000110';
		$vals['67']  = '10000101100';
		$vals['68']  = '10000100110';
		$vals['69']  = '10110010000';
		$vals['70']  = '10110000100';
		$vals['71']  = '10011010000';
		$vals['72']  = '10011000010';
		$vals['73']  = '10000110100';
		$vals['74']  = '10000110010';
		$vals['75']  = '11000010010';
		$vals['76']  = '11001010000';
		$vals['77']  = '11110111010';
		$vals['78']  = '11000010100';
		$vals['79']  = '10001111010';
		$vals['80']  = '10100111100';
		$vals['81']  = '10010111100';
		$vals['82']  = '10010011110';
		$vals['83']  = '10111100100';
		$vals['84']  = '10011110100';
		$vals['85']  = '10011110010';
		$vals['86']  = '11110100100';
		$vals['87']  = '11110010100';
		$vals['88']  = '11110010010';
		$vals['89']  = '11011011110';
		$vals['90']  = '11011110110';
		$vals['91']  = '11110110110';
		$vals['92']  = '10101111000';
		$vals['93']  = '10100011110';
		$vals['94']  = '10001011110';
		$vals['95']  = '10111101000';
		$vals['96']  = '10111100010';
		$vals['97']  = '11110101000';
		$vals['98']  = '11110100010';
		$vals['99']  = '10111011110';
		$vals['100'] = '10111101110';
		$vals['101'] = '11101011110';
		$vals['102'] = '11110101110';
		$vals['103'] = '11010000100';
		$vals['104'] = '11010010000';
		$vals['105'] = '11010011100';
		$vals['106'] = '1100011101011';

		$this->set  = $set;
		$this->vals = $vals;
	}
}

?>
