<?php

/**
 * Barcode128.class.php
 *
 * (c) Andreas Mueller <webmaster@am-wd.de>
 */

namespace AMWD;

/* ---                     DEPENDENCIES                          ---
----------------------------------------------------------------- */
function_exists('imagecreatetruecolor') || die('GD Library needed');

/**
 * Class to generate Barcodes with Code128
 *
 * @package    AMWD
 * @author     Andreas Mueller <webmaster@am-wd.de>
 * @copyright  (c) 2015 Andreas Mueller
 * @license    MIT - http://am-wd.de/index.php?p=about#license
 * @link       https://bitbucket.org/BlackyPanther/barcodes-128
 * @version    v1.0-20151222 | stable; no testcases
 */
class BarCode128
{
	/**
	 * Sets of character codecs
	 * @var string[]
	 */
	private $set    = array();
	
	/**
	 * Bit order for every char codec
	 * @var string[]
	 */
	private $vals   = array();
	
	/**
	 * Value contains current set
	 * @var string
	 */
	private $curSet = '';

	/**
	 * Array contains information for the checksum
	 * @var mixed[]
	 */
	private $checksum = array(
		'value' => 0,
		'data'  => array()
	);

	/**
	 * Array contains information about the encoding
	 * @var mixed[]
	 */
	private $encoding = array(
		'value'   => '',
		'data'    => array(),
		'strings' => array()
	);

	/**
	 * Array with infos for the binary code
	 * @var mixed[]
	 */
	private $code = array(
		'value' => '',
		'data'  => array()
	);

	/**
	 * The readable text above the barcode
	 * @var string
	 */
	private $text;

	/**
	 * Array with all dimensions
	 * @var int[]
	 */
	private $dims = array(
		'width'       => 0,
		'height'      => 0,
		'b_width'     => 0,
		'b_spacing'   => 0,
		'px_width'    => 0,
		'txt_spacing' => 0
	);

	/**
	 * Array containing measures of TTFBBox for barcode
	 * mixed[]
	 */
	private $bboxCode;
	
	/**
	 * Array containing measures of TTFBBox for text
	 * mixed[]
	 */
	private $bboxText;

	/**
	 * Path to font
	 * @var string
	 */
	private $font;
	
	/**
	 * Fontsize
	 * @var int
	 */
	private $fontSize;
	
	/**
	 * Calculated font size if text doesn't fit
	 * @var int
	 */
	private $calcFontSize;

	/**
	 * Flags for usage
	 * @var bool[]
	 */
	private $flags = array(
		'fontResize' => false,
		'showCode'   => false
	);

	/**
	 * The image itself
	 * @var string
	 */
	private $image;
	
	/**
	 * Defined colors
	 * @var string
	 */
	private $white, $black;


	/**
	 * Initializes a new instance of BarCode128
	 * 
	 * @param string  $code               Content of barcode
	 * @param integer [$height    = 150]  Height of the image in px
	 * @param string  [$font      = null] Path to font to use
	 * @param integer [$fontSize  = 10]   Fontsize to use
	 */
	public function __construct($code, $height = 150, $font = null, $fontSize = 10)
	{
		// set code for bar-parsing
		$this->setCodecSet();
		$this->Code($code);

		// default settings
		$this->BorderWidth(2);
		$this->BorderSpacing(10);
		$this->LineWidth(1);
		$this->TextSpacing(5);
		$this->CustomText('');
		$this->ShowCode(true);
		$this->FontResize(false);

		// add font, if given
		if ($font == null)
		{
			$this->font = null;
		}
		else
		{
			$this->addFont($font, $fontSize);
		}

		// calc width and height
		$this->Height($height - ($this->BorderWidth() + $this->BorderSpacing()) * 2);
		$this->Width($this->calcWidth());
	}


	// --- IMAGE AND DRAWS
	// ===========================================================================
	
	/**
	 * Set colors
	 * 
	 * @return void
	 */
	private function allocateColors()
	{
		$this->white = imagecolorallocate($this->image, 0xff, 0xff, 0xff);
		$this->black = imagecolorallocate($this->image, 0x00, 0x00, 0x00);
	}

	/**
	 * Return image as in buffers
	 * 
	 * @param  string [$type  = 'png'] Filetype (extension)
	 * 
	 * @return string The image itself
	 */
	public function get($type = 'png')
	{
		$this->Width($this->calcWidth());

		$this->image = imagecreatetruecolor($this->Width(), $this->Height());
		$this->allocateColors();
		imagefill($this->image, 0, 0, $this->white);

		if ($this->BorderWidth() > 0)
			$this->drawBorder();

		if (!empty($this->text))
			$this->drawText();

		$this->drawBarCode();

		if ($this->ShowCode())
			$this->drawCode();

		ob_start();

		switch ($type)
		{
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

	/**
	 * Print the image to screen
	 * 
	 * @param string [$type  = 'png'] Filetype (extension)
	 * 
	 * @return void
	 */
	public function draw($type = 'png')
	{
		switch ($type)
		{
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

	/**
	 * Save the image to file
	 * 
	 * @param string [$file  = 'barcode.png'] File path
	 * 
	 * @return void
	 */
	public function save($file = 'barcode.png')
	{
		$tmp = explode('.', $file);
		$type = $tmp[(count($tmp)-1)];

		$this->Width($this->calcWidth());

		$this->image = imagecreatetruecolor($this->Width(), $this->Height());
		$this->allocateColors();
		imagefill($this->image, 0, 0, $this->white);

		if ($this->BorderWidth() > 0)
			$this->drawBorder();

		if (!empty($this->text))
			$this->drawText();

		$this->drawBarCode();

		if ($this->ShowCode())
			$this->drawCode();

		switch ($type)
		{
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

	/**
	 * Draw the borders around the barcode
	 * 
	 * @return void
	 */
	private function drawBorder()
	{
		$w = $this->Width();
		$h = $this->Height();
		imagesetthickness($this->image, 1);
		for ($i = 0; $i < $this->BorderWidth(); $i++) {
			$x1 = $i; $y1 = $i;
			$x2 = $w-$i-1; $y2 = $h-$i-1;
			imageline($this->image, $x1, $y1, ($x2-1), $y1, $this->black);
			imageline($this->image, $x1, ($y1+1), $x1, $y2, $this->black);
			imageline($this->image, ($x1+1), $y2, $x2, $y2, $this->black);
			imageline($this->image, $x2, $y1, $x2, ($y2-1), $this->black);
		}
	}

	/**
	 * Draw the bars
	 * 
	 * @return void
	 */
	private function drawBarCode()
	{
		$str = $this->encoding['value'];
		$x = $this->BorderWidth() + $this->BorderSpacing();

		for ($i = 0; $i < strlen($str); $i++)
		{
			if ($str{$i} == 1)
			{
				$col = $this->black;
			}
			else
			{
				$col = $this->white;
			}

			for ($j = 0; $j < $this->LineWidth(); $j++)
			{
				// start
				if (!empty($this->text))
				{
					$y1 = $this->BorderWidth() + $this->BorderSpacing() + $this->TextSpacing() + $this->fontSize;
				}
				else
				{
					$y1 = $this->BorderWidth() + $this->BorderSpacing();
				}
				// end

				if ($this->ShowCode())
				{
					$y2 = ($this->Height() - ($this->BorderWidth() + $this->BorderSpacing() + $this->TextSpacing())) - $this->bboxCode['height'];
				}
				else
				{
					$y2 = $this->Height() - ($this->BorderWidth() + $this->BorderSpacing());
				}

				imagesetthickness($this->image, 1);
				imageline($this->image, $x, $y1, $x, $y2, $col);
				$x++;
			}
		}
	}

	/**
	 * Draw the encoded text below the bars
	 * 
	 * @return void
	 */
	private function drawCode()
	{
		if ($this->font == null)
				return;

		$x = (($this->Width() - $this->bboxCode['width']) / 2) - abs($this->bboxCode['x']);
		$y = $this->Height() - abs($this->bboxCode[1]) - $this->BorderWidth() - $this->BorderSpacing();

		imagettftext($this->image, $this->FontSize(), 0, $x, $y, $this->black, $this->getFont(), $this->Code());
	}

	/**
	 * Draw custom text above the bars
	 * 
	 * @return void
	 */
	private function drawText()
	{
		if ($this->font == null)
				return;

		$x = (($this->Width() -$this->bboxText['width']) / 2) - abs($this->bboxText['x']);
		$y = abs($this->bboxText[1]) + $this->BorderWidth() + $this->BorderSpacing() + $this->TextSpacing();

		imagettftext($this->image, $this->fontSize, 0, $x, $y, $this->black, $this->getFont(), $this->text);
	}



	// --- SIMPLE GETTER AND SETTER
	// ===========================================================================
	
	/**
	 * Gets or sets text for barcode.
	 * 
	 * @param  string [$code         = null] Text to set for barcode
	 * 
	 * @return string Text of barcode
	 */
	public function Code($code = null)
	{
		if ($code == null)
			return $this->code['value'];
		
		$this->code['value'] = strval($code);
		$this->generateData();
	}
	
	/**
	 * Gets or sets a value that indicates whether the text of the barcode should be printed or not.
	 * 
	 * @param  boolean [$flag  = null] Flag (true or false)
	 * @return boolean Current state of the flag
	 */
	public function ShowCode($flag = null)
	{
		if ($flag == null)
			return $this->flags['showCode'];
		
		if (!is_bool($flag))
			throw new \InvalidArgumentException('Show text can only be enabled or disabled');
		
		$this->flags['showCode'] = $flag;
	}

	/**
	 * Gets or sets custom text above the barcode.
	 * 
	 * @param  string [$text  = null] Custom text to be set
	 * @return string Custom text currently set
	 */
	public function CustomText($text = null)
	{
		if ($text == null)
			return $this->text;
		
		$this->text = trim($text);
	}
	
	/**
	 * Gets or sets a value that indicates whether the font should be resized or not.
	 * 
	 * @param  boolean [$flag  = null] Flag (true or false)
	 * @return boolean Current state of the flag
	 */
	public function FontResize($flag = null)
	{
		if ($flag == null)
			return $this->flags['fontResize'];
		
		if (!is_bool($flag))
			throw new \InvalidArgumentException('Font resize can only be enabeld or disabled');
		
		$this->flags['fontResize'] = $flag;
	}
	
	/**
	 * Gets or sets the line width
	 * 
	 * @param  integer [$px  = null] Line width in px
	 * @return integer Current line width
	 */
	public function LineWidth($px = null)
	{
		if ($px == null)
			return $this->dims['px_width'];
		
		if (intval($px) < 1)
			throw new \InvalidArgumentException('Line with should be one or more');
		
		$this->dims['px_width'] = intval($px);
		$this->Width($this->calcWidth());
	}
	
	/**
	 * Gets or sets the space to the borders
	 * 
	 * @param  integer [$px  = null] Border spacing in px
	 * @return integer Current spacing
	 */
	public function BorderSpacing($px = null)
	{
		if ($px == null)
			return $this->dims['b_spacing'];
		
		if (intval($px) < 0)
			throw new \InvalidArgumentException('Border spacing should be zero or more');
		
		$this->dims['b_spacing'] = intval($px);
		$this->Width($this->calcWidth());
	}

	/**
	 * Gets or sets the border width
	 * 
	 * @param  integer [$px         = null] Width of border in px
	 * @return integer Current border width
	 */
	public function BorderWidth($px = null)
	{
		if ($px == null)
			return $this->dims['b_width'];
		
		if (intval($px) < 0)
			throw new \InvalidArgumentException('Border width should be zero or more');
		
		$this->dims['b_width'] = intval($px);
		$this->Width($this->calcWidth());
	}

	/**
	 * Gets or sets the space between text letters
	 * 
	 * @param  integer [$px  = null] Letter spacing in px
	 * @return integer current value
	 */
	public function TextSpacing($px = null)
	{
		if ($px == null)
			return $this->dims['txt_spacing'];
		
		if (intval($px) < 0)
			throw new \InvalidArgumentException('Text spacing should be zero or more');
		
		$this->dims['txt_spacing'] = intval($px);
	}
	
	/**
	 * Add font with specific size
	 * 
	 * @param string  $font     Path to font-file
	 * @param integer $fontSize Size of font
	 * 
	 * @return void
	 */
	public function addFont($font, $fontSize)
	{
		$this->setFont($font);
		$this->initFontSize($fontSize);
	}

	/**
	 * Sets path to font
	 * 
	 * @param string $font Path to font
	 * 
	 * @return void
	 */
	private function setFont($font)
	{
		if (!file_exists($font))
				throw new \Exception('font does not exists');

		$this->font = $font;
	}
	
	/**
	 * Initializes size of font
	 * 
	 * @param integer $size Size of font
	 * 
	 * @return void
	 */
	private function initFontSize($size)
	{
		if (intval($size) < 1)
				throw new \Exception('font size less or equal zero');

		$this->fontSize = intval($size);
		$this->calcFontSize = intval($size);
	}

	/**
	 * Gets path of font if set
	 * 
	 * @return mixed Path to font or null
	 */
	public function getFont()
	{
		return $this->font;
	}
	
	/**
	 * Gets or sets fontsize
	 * 
	 * @param  integer [$px  = null] Fontsize on barcode
	 * 
	 * @return integer Current fontsize
	 */
	public function FontSize($px = null)
	{
		if ($px == null)
			return $this->calcFontSize;
		
		$this->calcFontSize = intval($px);
	}

	// --- IMAGE DIMENSIONS
	// ===========================================================================
	
	/**
	 * Gets or sets height of image
	 * 
	 * @param  integer [$px  = null] Height of image in px
	 * @return integer Current height in px
	 */
	public function Height($px = null)
	{
		if ($px == null)
			return $this->dims['height'];
		
		$px = intval($px);
		
		if ($px <= 0)
			throw new \InvalidArgumentException('Height should be greater than zero');
		
		$this->dims['height'] = $px;
	}
	
	/**
	 * Gets or sets (internal) width of image
	 * 
	 * @param  integer [$px  = null] Width of image in px
	 * @return integer Current width in px
	 */
	public function Width($px = null)
	{
		if ($px == null)
			return $this->dims['width'];

		$trace = debug_backtrace();

		if (!isset($trace[1]['class']) || $trace[1]['class'] != __CLASS__)
			throw new \BadMethodCallException('Set width only internal allowed');
			
		$this->dims['width'] = intval($px);
		
		if ($this->font != false)
		{
			if ($this->FontResize())
			{
				$width = $this->Width() - ($this->BorderWidth() + $this->BorderSpacing() + $this->TextSpacing() * 2);
				$this->FontSize($this->calcFontSize($width));
			}
			else
			{
				$this->bboxCode = $this->calcTTFBBox($this->FontSize(), $this->getFont(), $this->Code());
			}
			$this->bboxText = $this->calcTTFBBox($this->fontSize, $this->getFont(), $this->text);
		}
	}


	// --- CALCULATIONS
	// ===========================================================================
	
	/**
	 * Calculate width of the image
	 * 
	 * @return integer Width in px
	 */
	private function calcWidth()
	{
		$len = strlen($this->encoding['value']);
		$px  = $this->LineWidth();
		$b   = $this->BorderWidth();
		$sp  = $this->BorderSpacing();

		return ($len * $px + ($b + $sp) * 2);
	}

	/**
	 * Calculate fontsize that fits in the space
	 * 
	 * @param  integer $width Width of space available
	 * 
	 * @return integer Fontsize that is possible
	 */
	private function calcFontSize($width)
	{
		$i = 1; $run = true;
		while ($run) 
		{
			$bbox = $this->calcTTFBBox($i, $this->getFont(), $this->Code());
			if ($bboxCode['width'] < $width)
			{
				$i++;
			}
			else
			{
				$i--;
				$bbox = $this->calcTTFBBox($i, $this->getFont(), $this->Code());
				$run = false;
			}
		}
		$this->bboxCode = $bbox;
		return $i;
	}

	/**
	 * Calculate font box
	 * 
	 * @param  integer $fontSize Size of font
	 * @param  string  $font     Path to font
	 * @param  string  $text     Text to print
	 * 
	 * @return object  Calculated border-box for text
	 */
	private function calcTTFBBox($fontSize, $font, $text)
	{
		$bbox = imagettfbbox($fontSize, 0, $font, $text);
		if($bbox[0] >= -1)
		{
			$bbox['x'] = abs($bbox[0] + 1) * -1;
		}
		else
		{
			$bbox['x'] = abs($bbox[0] + 2);
		}
		
		$bbox['width'] = abs($bbox[2] - $bbox[0]);
		if($bbox[0] < -1)
		{
			$bbox['width'] = abs($bbox[2]) + abs($bbox[0]) - 1;
		}
		
		$bbox['y'] = abs($bbox[5] + 1);
		$bbox['height'] = abs($bbox[7]) - abs($bbox[1]);
		if($bbox[3] > 0)
		{
			$bbox['height'] = abs($bbox[7] - $bbox[1]) - 1;
		}
		
		return $bbox;
	}


	// --- DATA FOR ENCODING
	// ===========================================================================
	
	/**
	 * Generates ones and zeros for the barcode itself
	 * 
	 * @return void
	 */
	private function generateData()
	{
		$code = $this->Code();
		$data = array();
		for ($i = 0; $i < strlen($code); $i++)
		{
			if ($i == (strlen($code) - 1))
			{
				// last char
				$val = $code{$i};
				$data[] = $this->replaceSpecialChars($val);
			}
			else
			{
				if (is_numeric($code{$i}) && is_numeric($code{$i+1}))
				{
					$data[] = $code{$i}.$code{$i+1};
					$i++;
				}
				else
				{
					$val = $code{$i};
					$data[] = $this->replaceSpecialChars($val);
				}
			}
		}
		
		$this->code['data'] = $data;

		if (isset($data[0]))
				$this->setStartSet($this->getCharSet($data[0]));

		for ($i = 0; $i < count($data); ++$i)
		{
			$set = $this->getCharSet($data[$i]);
			if ($set != $this->curSet)
				$this->changeSet($set);

			$val = $this->getCharVal($set, $data[$i]);
			$this->addChecksum($val);
			$this->addEncoding($val);
		}

		$chk = $this->checksum['value'];
		$this->addEncoding($chk % 103);
		$this->addEncoding(106);
	}

	/**
	 * Replace special chars from code
	 * 
	 * @param  string $val Char itself
	 * 
	 * @return string Corresponding replacement
	 */
	private function replaceSpecialChars($val)
	{
		switch ($val)
		{
			case ' ':  return 'SP';
			case "\t": return 'HT';
			case "\f": return 'FF';
			default:   return $val;
		}
	}

	/**
	 * Get value for char in charset
	 * 
	 * @param  string  $set  Characterset
	 * @param  string  $char Char to get value for
	 * 
	 * @return integer Value for char in charset
	 */
	private function getCharVal($set, $char)
	{
		return $this->set[$set][$char];
	}

	/**
	 * Change to another characterset
	 * 
	 * @param string $set New characterset
	 * 
	 * @return void
	 */
	private function changeSet($set)
	{
		$this->curSet = $set;
		switch ($set)
		{
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

	/**
	 * Get possible charset for character
	 * 
	 * @param  string $char Character
	 * 
	 * @return string Best characterset for char
	 */
	private function getCharSet($char)
	{
		$set = $this->set;
		$curSet = $this->curSet;
		$sets = array('A', 'B', 'C');

		if (!empty($curSet))
		{
			if (array_key_exists($char, $set[$curSet]))
				return $curSet;

			$index = array_search($curSet, $sets);
			unset($sets[$index]);
			sort($sets);
		}

		for ($i = 0; $i < count($sets); ++$i)
		{
			if (array_key_exists($char, $set[$sets[$i]]))
				return $sets[$i];
		}
	}

	/**
	 * Set starting characterset
	 * 
	 * @param string $set Characterset
	 * 
	 * @return void
	 */
	private function setStartSet($set)
	{
		$this->curSet = $set;
		switch ($set)
		{
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

	/**
	 * Adding checksum to binary code
	 * 
	 * @param integer $val Checksum
	 * 
	 * @return void
	 */
	private function addChecksum($val)
	{
		$chk = $this->checksum;
		$count = count($chk['data']);

		if ($count == 0)
		{
			$chk['data'][] = $val;
		}
		else
		{
			$chk['data'][] = ($val * $count);
		}
		
		$chk['value'] = array_sum($chk['data']);

		$this->checksum = $chk;
	}

	/**
	 * Adding encoding
	 * 
	 * @param string $val Encoding
	 * 
	 * @return void
	 */
	private function addEncoding($val)
	{
		$enc = $this->encoding;

		$enc['data'][] = $val;
		$enc['value'] .= $this->getVal($val);
		$enc['strings'][] = $this->getVal($val);

		$this->encoding = $enc;
	}
	
	/**
	 * Return value of specific count
	 * 
	 * @param  integer $val Number of position
	 * 
	 * @return string  Value at position
	 */
	public function getVal($val)
	{
		return $this->vals[$val];
	}

	/**
	 * Set characterset
	 * 
	 * @return void
	 */
	private function setCodecSet()
	{
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
