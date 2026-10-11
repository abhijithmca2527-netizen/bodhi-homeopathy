<?php
/*******************************************************************************
* FPDF                                                                         *
* Version: 1.86                                                                *
* Date:    2023-06-25                                                          *
* Author:  Olivier PLATHEY                                                     *
*******************************************************************************/

define('FPDF_VERSION', '1.86');

class FPDF
{
	protected $page;               // current page number
	protected $n;                  // current object number
	protected $offsets;            // array of object offsets
	protected $buffer;             // buffer holding in-memory PDF
	protected $pages;              // array containing pages
	protected $state;              // current document state
	protected $compress;           // compression flag
	protected $k;                  // scale factor (number of points in user unit)
	protected $DefOrientation;     // default orientation
	protected $CurOrientation;     // current orientation
	protected $StdPageSizes;       // standard page sizes
	protected $DefPageSize;        // default page size
	protected $CurPageSize;        // current page size
	protected $CurRotation;        // current page rotation
	protected $PageInfo;           // page-related data
	protected $wPt, $hPt;          // dimensions of current page in points
	protected $w, $h;              // dimensions of current page in user units
	protected $lMargin;            // left margin
	protected $tMargin;            // top margin
	protected $rMargin;            // right margin
	protected $bMargin;            // page break margin
	protected $cMargin;            // cell margin
	protected $x, $y;              // current position in user units
	protected $lasth;              // height of last printed cell
	protected $LineWidth;          // line width in user units
	protected $fontpath;           // path containing fonts
	protected $CoreFonts;          // array of core font names
	protected $fonts;              // array of used fonts
	protected $FontFiles;          // array of font files
	protected $encodings;          // array of encodings
	protected $cmaps;              // array of ToUnicode CMaps
	protected $FontFamily;         // current font family
	protected $FontStyle;          // current font style
	protected $underline;          // underlining flag
	protected $CurrentFont;        // current font info
	protected $FontSizePt;         // current font size in points
	protected $FontSize;           // current font size in user units
	protected $DrawColor;          // commands for drawing color
	protected $FillColor;          // commands for filling color
	protected $TextColor;          // commands for text color
	protected $ColorFlag;          // indicates whether fill and text colors are different
	protected $WithAlpha;          // indicates whether alpha channel is used
	protected $ws;                 // word spacing
	protected $images;             // array of used images
	protected $PageLinks;          // array of links in pages
	protected $links;              // array of internal links
	protected $AutoPageBreak;      // automatic page breaking
	protected $PageBreakTrigger;   // threshold used to trigger page breaks
	protected $InHeader;           // flag set when processing header
	protected $InFooter;           // flag set when processing footer
	protected $AliasNbPages;       // alias for total number of pages
	protected $ZoomMode;           // zoom mode
	protected $LayoutMode;         // layout mode
	protected $metadata;           // PDF metadata
	protected $PDFVersion;         // PDF version number

	function __construct($orientation='P', $unit='mm', $size='A4')
	{
		$this->_dochecks();
		$this->state = 0;
		$this->page = 0;
		$this->n = 2;
		$this->buffer = '';
		$this->pages = array();
		$this->PageInfo = array();
		$this->fonts = array();
		$this->FontFiles = array();
		$this->encodings = array();
		$this->cmaps = array();
		$this->images = array();
		$this->links = array();
		$this->InHeader = false;
		$this->InFooter = false;
		$this->lasth = 0;
		$this->FontFamily = '';
		$this->FontStyle = '';
		$this->FontSizePt = 12;
		$this->underline = false;
		$this->DrawColor = '0 G';
		$this->FillColor = '0 g';
		$this->TextColor = '0 g';
		$this->ColorFlag = false;
		$this->WithAlpha = false;
		$this->ws = 0;
		$this->CoreFonts = array('courier', 'helvetica', 'times', 'symbol', 'zapfdingbats');
		if($unit=='pt')
			$this->k = 1;
		elseif($unit=='mm')
			$this->k = 72/25.4;
		elseif($unit=='cm')
			$this->k = 72/2.54;
		elseif($unit=='in')
			$this->k = 72;
		else
			$this->Error('Incorrect unit: '.$unit);
		$this->StdPageSizes = array('a3'=>array(841.89,1190.55), 'a4'=>array(595.28,841.89), 'a5'=>array(420.94,595.28),
			'letter'=>array(612,792), 'legal'=>array(612,1008));
		$size = $this->_getpagesize($size);
		$this->DefPageSize = $size;
		$this->CurPageSize = $size;
		$orientation = strtolower($orientation);
		if($orientation=='p' || $orientation=='portrait')
		{
			$this->DefOrientation = 'P';
			$this->w = $size[0];
			$this->h = $size[1];
		}
		elseif($orientation=='l' || $orientation=='landscape')
		{
			$this->DefOrientation = 'L';
			$this->w = $size[1];
			$this->h = $size[0];
		}
		else
			$this->Error('Incorrect orientation: '.$orientation);
		$this->CurOrientation = $this->DefOrientation;
		$this->wPt = $this->w*$this->k;
		$this->hPt = $this->h*$this->k;
		$this->CurRotation = 0;
		$margin = 28.35/$this->k;
		$this->SetMargins($margin,$margin);
		$this->cMargin = $margin/10;
		$this->LineWidth = .567/$this->k;
		$this->SetAutoPageBreak(true,2*$margin);
		$this->SetDisplayMode('default');
		$this->SetCompression(true);
		$this->PDFVersion = '1.3';
	}

	function SetMargins($left, $top, $right=null)
	{
		$this->lMargin = $left;
		$this->tMargin = $top;
		if($right===null)
			$this->rMargin = $left;
		else
			$this->rMargin = $right;
	}

	function SetLeftMargin($margin)
	{
		$this->lMargin = $margin;
		if($this->page>0 && $this->x<$margin)
			$this->x = $margin;
	}

	function SetTopMargin($margin)
	{
		$this->tMargin = $margin;
	}

	function SetRightMargin($margin)
	{
		$this->rMargin = $margin;
	}

	function SetAutoPageBreak($auto, $margin=0)
	{
		$this->AutoPageBreak = $auto;
		$this->bMargin = $margin;
		$this->PageBreakTrigger = $this->h-$margin;
	}

	function SetDisplayMode($zoom, $layout='default')
	{
		if($zoom=='fullpage' || $zoom=='fullwidth' || $zoom=='real' || $zoom=='default' || !is_string($zoom))
			$this->ZoomMode = $zoom;
		else
			$this->Error('Incorrect zoom mode: '.$zoom);
		if($layout=='single' || $layout=='continuous' || $layout=='two' || $layout=='default')
			$this->LayoutMode = $layout;
		else
			$this->Error('Incorrect layout mode: '.$layout);
	}

	function SetCompression($compress)
	{
		if(function_exists('gzcompress'))
			$this->compress = $compress;
		else
			$this->compress = false;
	}

	function SetTitle($title, $isUTF8=false)
	{
		$this->metadata['Title'] = $isUTF8 ? $title : $this->_UTF8encode($title);
	}

	function SetAuthor($author, $isUTF8=false)
	{
		$this->metadata['Author'] = $isUTF8 ? $author : $this->_UTF8encode($author);
	}

	function SetSubject($subject, $isUTF8=false)
	{
		$this->metadata['Subject'] = $isUTF8 ? $subject : $this->_UTF8encode($subject);
	}

	function SetKeywords($keywords, $isUTF8=false)
	{
		$this->metadata['Keywords'] = $isUTF8 ? $keywords : $this->_UTF8encode($keywords);
	}

	function SetCreator($creator, $isUTF8=false)
	{
		$this->metadata['Creator'] = $isUTF8 ? $creator : $this->_UTF8encode($creator);
	}

	function AliasNbPages($alias='{nb}')
	{
		$this->AliasNbPages = $alias;
	}

	function Error($msg)
	{
		throw new Exception('FPDF error: '.$msg);
	}

	function Close()
	{
		if($this->state==3)
			return;
		if($this->page==0)
			$this->AddPage();
		$this->InFooter = true;
		$this->Footer();
		$this->InFooter = false;
		$this->_endpage();
		$this->_enddoc();
	}

	function AddPage($orientation='', $size='', $rotation=0)
	{
		if($this->state==3)
			$this->Error('The document is closed');
		$family = $this->FontFamily;
		$style = $this->FontStyle.($this->underline ? 'U' : '');
		$fontsize = $this->FontSizePt;
		$lw = $this->LineWidth;
		$dc = $this->DrawColor;
		$fc = $this->FillColor;
		$tc = $this->TextColor;
		$cf = $this->ColorFlag;
		if($this->page>0)
		{
			$this->InFooter = true;
			$this->Footer();
			$this->InFooter = false;
			$this->_endpage();
		}
		$this->_beginpage($orientation,$size,$rotation);
		$this->_pageentry();
		$this->LineWidth = $lw;
		$this->_out(sprintf('%.2F w',$lw*$this->k));
		if($family)
			$this->SetFont($family,$style,$fontsize);
		$this->DrawColor = $dc;
		if($dc!='0 G')
			$this->_out($dc);
		$this->FillColor = $fc;
		if($fc!='0 g')
			$this->_out($fc);
		$this->TextColor = $tc;
		$this->ColorFlag = $cf;
		$this->InHeader = true;
		$this->Header();
		$this->InHeader = false;
		if($this->LineWidth!=$lw)
		{
			$this->LineWidth = $lw;
			$this->_out(sprintf('%.2F w',$lw*$this->k));
		}
		if($family)
			$this->SetFont($family,$style,$fontsize);
		if($this->DrawColor!=$dc)
		{
			$this->DrawColor = $dc;
			$this->_out($dc);
		}
		if($this->FillColor!=$fc)
		{
			$this->FillColor = $fc;
			$this->_out($fc);
		}
		$this->TextColor = $tc;
		$this->ColorFlag = $cf;
	}

	function Header()
	{
	}

	function Footer()
	{
	}

	function PageNo()
	{
		return $this->page;
	}

	function SetDrawColor($r, $g=null, $b=null)
	{
		if(($r==0 && $g==0 && $b==0) || $g===null)
			$this->DrawColor = sprintf('%.3F G',$r/255);
		else
			$this->DrawColor = sprintf('%.3F %.3F %.3F RG',$r/255,$g/255,$b/255);
		if($this->page>0)
			$this->_out($this->DrawColor);
	}

	function SetFillColor($r, $g=null, $b=null)
	{
		if(($r==0 && $g==0 && $b==0) || $g===null)
			$this->FillColor = sprintf('%.3F g',$r/255);
		else
			$this->FillColor = sprintf('%.3F %.3F %.3F rg',$r/255,$g/255,$b/255);
		$this->ColorFlag = ($this->FillColor!=$this->TextColor);
		if($this->page>0)
			$this->_out($this->FillColor);
	}

	function SetTextColor($r, $g=null, $b=null)
	{
		if(($r==0 && $g==0 && $b==0) || $g===null)
			$this->TextColor = sprintf('%.3F g',$r/255);
		else
			$this->TextColor = sprintf('%.3F %.3F %.3F rg',$r/255,$g/255,$b/255);
		$this->ColorFlag = ($this->FillColor!=$this->TextColor);
	}

	function GetStringWidth($s)
	{
		$s = (string)$s;
		$cw = $this->CurrentFont['cw'];
		$w = 0;
		$l = strlen($s);
		for($i=0;$i<$l;$i++)
			$w += $cw[$s[$i]];
		return $w*$this->FontSize/1000;
	}

	function SetLineWidth($width)
	{
		$this->LineWidth = $width;
		if($this->page>0)
			$this->_out(sprintf('%.2F w',$width*$this->k));
	}

	function Line($x1, $y1, $x2, $y2)
	{
		$this->_out(sprintf('%.2F %.2F m %.2F %.2F l S',$x1*$this->k,($this->h-$y1)*$this->k,$x2*$this->k,($this->h-$y2)*$this->k));
	}

	function Rect($x, $y, $w, $h, $style='')
	{
		if($style=='F')
			$op = 'f';
		elseif($style=='FD' || $style=='DF')
			$op = 'B';
		else
			$op = 'S';
		$this->_out(sprintf('%.2F %.2F %.2F %.2F re %s',$x*$this->k,($this->h-$y)*$this->k,$w*$this->k,-$h*$this->k,$op));
	}

	function AddFont($family, $style='', $file='', $dir='')
	{
		$family = strtolower($family);
		if($file=='')
			$file = str_replace(' ','',$family).strtolower($style).'.php';
		$style = strtoupper($style);
		if($style=='IB')
			$style = 'BI';
		$fontkey = $family.$style;
		if(isset($this->fonts[$fontkey]))
			return;
		$info = $this->_loadfont($file,$dir);
		$info['i'] = count($this->fonts)+1;
		$this->fonts[$fontkey] = $info;
	}

	function SetFont($family, $style='', $size=0)
	{
		if($family=='')
			$family = $this->FontFamily;
		else
			$family = strtolower($family);
		$style = strtoupper($style);
		if(strpos($style,'U')!==false)
		{
			$this->underline = true;
			$style = str_replace('U','',$style);
		}
		else
			$this->underline = false;
		if($style=='IB')
			$style = 'BI';
		if($size==0)
			$size = $this->FontSizePt;
		if($this->FontFamily==$family && $this->FontStyle==$style && $this->FontSizePt==$size)
			return;
		$fontkey = $family.$style;
		if(!isset($this->fonts[$fontkey]))
		{
			if(in_array($family,$this->CoreFonts))
			{
				if($family=='symbol' || $family=='zapfdingbats')
					$style = '';
				$fontkey = $family.$style;
				if(!isset($this->fonts[$fontkey]))
					$this->_addcorefont($family,$style);
			}
			else
				$this->Error('Undefined font: '.$family.' '.$style);
		}
		$this->FontFamily = $family;
		$this->FontStyle = $style;
		$this->FontSizePt = $size;
		$this->FontSize = $size/$this->k;
		$this->CurrentFont = $this->fonts[$fontkey];
		if($this->page>0)
			$this->_out(sprintf('BT /F%d %.2F Tf ET',$this->CurrentFont['i'],$this->FontSizePt));
	}

	function SetFontSize($size)
	{
		if($this->FontSizePt==$size)
			return;
		$this->FontSizePt = $size;
		$this->FontSize = $size/$this->k;
		if($this->page>0)
		{
			if(!isset($this->CurrentFont))
				$this->Error('Cannot set font size: no font has been selected');
			$this->_out(sprintf('BT /F%d %.2F Tf ET',$this->CurrentFont['i'],$this->FontSizePt));
		}
	}

	function AddLink()
	{
		$n = count($this->links)+1;
		$this->links[$n] = array(0, 0);
		return $n;
	}

	function SetLink($link, $y=0, $page=-1)
	{
		if($y==-1)
			$y = $this->y;
		if($page==-1)
			$page = $this->page;
		$this->links[$link] = array($page, $y);
	}

	function Link($x, $y, $w, $h, $link)
	{
		$this->PageLinks[$this->page][] = array($x*$this->k, $this->hPt-$y*$this->k, $w*$this->k, $h*$this->k, $link);
	}

	function Text($x, $y, $txt)
	{
		if(!isset($this->CurrentFont))
			$this->Error('No font has been set');
		$s = sprintf('BT %.2F %.2F Td (%s) ET',$x*$this->k,($this->h-$y)*$this->k,$this->_escape($txt));
		if($this->underline && $txt!='')
			$s .= ' '.$this->_dounderline($x,$y,$txt);
		if($this->ColorFlag)
			$s = 'q '.$this->TextColor.' '.$s.' Q';
		$this->_out($s);
	}

	function AcceptPageBreak()
	{
		return $this->AutoPageBreak;
	}

	function Cell($w, $h=0, $txt='', $border=0, $ln=0, $align='', $fill=false, $link='')
	{
		$k = $this->k;
		if($this->y+$h>$this->PageBreakTrigger && !$this->InHeader && !$this->InFooter && $this->AcceptPageBreak())
		{
			$x = $this->x;
			$ws = $this->ws;
			if($ws>0)
			{
				$this->ws = 0;
				$this->_out('0 Tw');
			}
			$this->AddPage($this->CurOrientation,$this->CurPageSize,$this->CurRotation);
			$this->x = $x;
			if($ws>0)
			{
				$this->ws = $ws;
				$this->_out(sprintf('%.3F Tw',$ws*$k));
			}
		}
		if($w==0)
			$w = $this->w-$this->rMargin-$this->x;
		$s = '';
		if($fill || $border==1)
		{
			if($fill)
				$op = ($border==1) ? 'B' : 'f';
			else
				$op = 'S';
			$s = sprintf('%.2F %.2F %.2F %.2F re %s ',$this->x*$k,($this->h-$this->y)*$k,$w*$k,-$h*$k,$op);
		}
		if(is_string($border))
		{
			$x = $this->x;
			$y = $this->y;
			if(strpos($border,'L')!==false)
				$s .= sprintf('%.2F %.2F m %.2F %.2F l S ',$x*$k,($this->h-$y)*$k,$x*$k,($this->h-($y+$h))*$k);
			if(strpos($border,'T')!==false)
				$s .= sprintf('%.2F %.2F m %.2F %.2F l S ',$x*$k,($this->h-$y)*$k,($x+$w)*$k,($this->h-$y)*$k);
			if(strpos($border,'R')!==false)
				$s .= sprintf('%.2F %.2F m %.2F %.2F l S ',($x+$w)*$k,($this->h-$y)*$k,($x+$w)*$k,($this->h-($y+$h))*$k);
			if(strpos($border,'B')!==false)
				$s .= sprintf('%.2F %.2F m %.2F %.2F l S ',$x*$k,($this->h-($y+$h))*$k,($x+$w)*$k,($this->h-($y+$h))*$k);
		}
		if($txt!=='')
		{
			if(!isset($this->CurrentFont))
				$this->Error('No font has been set');
			if($align=='R')
				$dx = $w-$this->cMargin-$this->GetStringWidth($txt);
			elseif($align=='C')
				$dx = ($w-$this->GetStringWidth($txt))/2;
			else
				$dx = $this->cMargin;
			if($this->ColorFlag)
				$s .= 'q '.$this->TextColor.' ';
			$s .= sprintf('BT %.2F %.2F Td (%s) ET',($this->x+$dx)*$k,($this->h-($this->y+.5*$h+.3*$this->FontSize))*$k,$this->_escape($txt));
			if($this->underline)
				$s .= ' '.$this->_dounderline($this->x+$dx,$this->y+.5*$h+.3*$this->FontSize,$txt);
			if($this->ColorFlag)
				$s .= ' Q';
			if($link)
				$this->Link($this->x+$dx,$this->y+.5*$h-.5*$this->FontSize,$this->GetStringWidth($txt),$this->FontSize,$link);
		}
		if($s)
			$this->_out($s);
		$this->lasth = $h;
		if($ln>0)
		{
			$this->y += $h;
			if($ln==1)
				$this->x = $this->lMargin;
		}
		else
			$this->x += $w;
	}

	function MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false)
	{
		if(!isset($this->CurrentFont))
			$this->Error('No font has been set');
		$cw = $this->CurrentFont['cw'];
		if($w==0)
			$w = $this->w-$this->rMargin-$this->x;
		$wmax = ($w-2*$this->cMargin)*1000/$this->FontSize;
		$s = str_replace("\r",'',(string)$txt);
		$nb = strlen($s);
		if($nb>0 && $s[$nb-1]=="\n")
			$nb--;
		$b = 0;
		if($border)
		{
			if($border==1)
			{
				$border = 'LTRB';
				$b = 'LRT';
				$b2 = 'LR';
			}
			else
			{
				$b2 = '';
				if(strpos($border,'L')!==false)
					$b2 .= 'L';
				if(strpos($border,'R')!==false)
					$b2 .= 'R';
				$b = (strpos($border,'T')!==false) ? $b2.'T' : $b2;
			}
		}
		$sep = -1;
		$i = 0;
		$j = 0;
		$l = 0;
		$ns = 0;
		$nl = 1;
		while($i<$nb)
		{
			$c = $s[$i];
			if($c=="\n")
			{
				if($this->ws>0)
				{
					$this->ws = 0;
					$this->_out('0 Tw');
				}
				$this->Cell($w,$h,substr($s,$j,$i-$j),$b,2,$align,$fill);
				$i++;
				$sep = -1;
				$j = $i;
				$l = 0;
				$ns = 0;
				$nl++;
				if($border && $nl==2)
					$b = $b2;
				continue;
			}
			if($c==' ')
			{
				$sep = $i;
				$ls = $l;
				$ns++;
			}
			$l += $cw[$c];
			if($l>$wmax)
			{
				if($sep==-1)
				{
					if($i==$j)
						$i++;
					if($this->ws>0)
					{
						$this->ws = 0;
						$this->_out('0 Tw');
					}
					$this->Cell($w,$h,substr($s,$j,$i-$j),$b,2,$align,$fill);
				}
				else
				{
					if($align=='J')
					{
						$this->ws = ($ns>1) ? ($wmax-$ls)/1000*$this->FontSize/($ns-1) : 0;
						$this->_out(sprintf('%.3F Tw',$this->ws*$this->k));
					}
					$this->Cell($w,$h,substr($s,$j,$sep-$j),$b,2,$align,$fill);
					$i = $sep+1;
				}
				$sep = -1;
				$j = $i;
				$l = 0;
				$ns = 0;
				$nl++;
				if($border && $nl==2)
					$b = $b2;
			}
			else
				$i++;
		}
		if($this->ws>0)
		{
			$this->ws = 0;
			$this->_out('0 Tw');
		}
		if($border && strpos($border,'B')!==false)
			$b .= 'B';
		$this->Cell($w,$h,substr($s,$j,$i-$j),$b,2,$align,$fill);
		$this->x = $this->lMargin;
	}

	function Write($h, $txt, $link='')
	{
		if(!isset($this->CurrentFont))
			$this->Error('No font has been set');
		$cw = $this->CurrentFont['cw'];
		$w = $this->w-$this->rMargin-$this->x;
		$wmax = ($w-2*$this->cMargin)*1000/$this->FontSize;
		$s = str_replace("\r",'',(string)$txt);
		$nb = strlen($s);
		$sep = -1;
		$i = 0;
		$j = 0;
		$l = 0;
		$nl = 1;
		while($i<$nb)
		{
			$c = $s[$i];
			if($c=="\n")
			{
				$this->Cell($w,$h,substr($s,$j,$i-$j),0,2,'',false,$link);
				$i++;
				$sep = -1;
				$j = $i;
				$l = 0;
				if($nl==1)
				{
					$this->x = $this->lMargin;
					$w = $this->w-$this->rMargin-$this->x;
					$wmax = ($w-2*$this->cMargin)*1000/$this->FontSize;
				}
				$nl++;
				continue;
			}
			if($c==' ')
				$sep = $i;
			$l += $cw[$c];
			if($l>$wmax)
			{
				if($sep==-1)
				{
					if($this->x>$this->lMargin)
					{
						$this->x = $this->lMargin;
						$this->y += $h;
						$w = $this->w-$this->rMargin-$this->x;
						$wmax = ($w-2*$this->cMargin)*1000/$this->FontSize;
						$i++;
						$nl++;
						continue;
					}
					if($i==$j)
						$i++;
					$this->Cell($w,$h,substr($s,$j,$i-$j),0,2,'',false,$link);
				}
				else
				{
					$this->Cell($w,$h,substr($s,$j,$sep-$j),0,2,'',false,$link);
					$i = $sep+1;
				}
				$sep = -1;
				$j = $i;
				$l = 0;
				if($nl==1)
				{
					$this->x = $this->lMargin;
					$w = $this->w-$this->rMargin-$this->x;
					$wmax = ($w-2*$this->cMargin)*1000/$this->FontSize;
				}
				$nl++;
			}
			else
				$i++;
		}
		if($i!=$j)
			$this->Cell($l/1000*$this->FontSize,$h,substr($s,$j),0,0,'',false,$link);
	}

	function Ln($h=null)
	{
		$this->x = $this->lMargin;
		if($h===null)
			$this->y += $this->lasth;
		else
			$this->y += $h;
	}

	function GetX()
	{
		return $this->x;
	}

	function SetX($x)
	{
		if($x>=0)
			$this->x = $x;
		else
			$this->x = $this->w+$x;
	}

	function GetY()
	{
		return $this->y;
	}

	function SetY($y, $resetX=true)
	{
		if($resetX)
			$this->x = $this->lMargin;
		if($y>=0)
			$this->y = $y;
		else
			$this->y = $this->h+$y;
	}

	function SetXY($x, $y)
	{
		$this->SetX($x);
		$this->SetY($y,false);
	}

	function Output($dest='', $name='', $isUTF8=false)
	{
		$this->Close();
		if(strlen($name)==0)
		{
			$name = 'doc.pdf';
			$dest = 'I';
		}
		$dest = strtoupper($dest);
		if($dest=='')
			$dest = 'I';
		if($dest=='I')
		{
			$this->_checkoutput();
			if(PHP_SAPI!='cli')
			{
				header('Content-Type: application/pdf');
				header('Content-Disposition: inline; '.$this->_httpencode('filename',$name,$isUTF8));
				header('Cache-Control: private, max-age=0, must-revalidate');
				header('Pragma: public');
			}
			echo $this->buffer;
		}
		elseif($dest=='D')
		{
			$this->_checkoutput();
			header('Content-Type: application/x-download');
			header('Content-Disposition: attachment; '.$this->_httpencode('filename',$name,$isUTF8));
			header('Cache-Control: private, max-age=0, must-revalidate');
			header('Pragma: public');
			echo $this->buffer;
		}
		elseif($dest=='F')
		{
			$f = fopen($name,'wb');
			if(!$f)
				$this->Error('Unable to create output file: '.$name);
			fwrite($f,$this->buffer,strlen($this->buffer));
			fclose($f);
		}
		elseif($dest=='S')
			return $this->buffer;
		else
			$this->Error('Incorrect output destination: '.$dest);
		return '';
	}

	protected function _dochecks()
	{
		if(ini_get('mbstring.func_overload') & 2)
			$this->Error('mbstring overloading must be disabled in php.ini');
	}

	protected function _checkoutput()
	{
		if(PHP_SAPI!='cli')
		{
			if(headers_sent($file,$line))
				$this->Error("Some data has already been output, can't send PDF file (output started at $file:$line)");
		}
		if(ob_get_length())
		{
			if(preg_match('/^[\x00-\x20]*$/',ob_get_contents()))
				ob_clean();
			else
				$this->Error("Some data has already been output, can't send PDF file");
		}
	}

	protected function _getpagesize($size)
	{
		if(is_string($size))
		{
			$size = strtolower($size);
			if(!isset($this->StdPageSizes[$size]))
				$this->Error('Unknown page size: '.$size);
			$a = $this->StdPageSizes[$size];
			return array($a[0]/$this->k, $a[1]/$this->k);
		}
		else
		{
			if($size[0]>$size[1])
				return array($size[1], $size[0]);
			else
				return $size;
		}
	}

	protected function _beginpage($orientation, $size, $rotation)
	{
		$this->page++;
		$this->pages[$this->page] = '';
		$this->PageInfo[$this->page] = array();
		$this->state = 2;
		$this->x = $this->lMargin;
		$this->y = $this->tMargin;
		$this->FontFamily = '';
		if($orientation=='')
			$orientation = $this->DefOrientation;
		else
			$orientation = strtoupper($orientation[0]);
		if($size=='')
			$size = $this->DefPageSize;
		else
			$size = $this->_getpagesize($size);
		if($orientation!=$this->CurOrientation || $size[0]!=$this->CurPageSize[0] || $size[1]!=$this->CurPageSize[1])
		{
			if($orientation=='P')
			{
				$this->w = $size[0];
				$this->h = $size[1];
			}
			else
			{
				$this->w = $size[1];
				$this->h = $size[0];
			}
			$this->wPt = $this->w*$this->k;
			$this->hPt = $this->h*$this->k;
			$this->PageBreakTrigger = $this->h-$this->bMargin;
			$this->CurOrientation = $orientation;
			$this->CurPageSize = $size;
		}
		if($orientation!=$this->DefOrientation || $size[0]!=$this->DefPageSize[0] || $size[1]!=$this->DefPageSize[1])
			$this->PageInfo[$this->page]['size'] = array($this->wPt, $this->hPt);
		if($rotation!=0)
		{
			if($rotation%90!=0)
				$this->Error('Incorrect rotation value: '.$rotation);
			$this->CurRotation = $rotation;
			$this->PageInfo[$this->page]['rotation'] = $rotation;
		}
	}

	protected function _endpage()
	{
		$this->state = 1;
	}

	protected function _loadfont($font, $dir)
	{
		$this->Error('External fonts require font directory');
	}

	protected function _addcorefont($family, $style)
	{
		$fontkey = $family.$style;
		$cw = array();
		// Standard Helvetica / Times / Courier metric approximations
		for($i=0;$i<256;$i++) $cw[chr($i)] = 600;
		// Standard Helvetica character widths
		$cw[' '] = 278; $cw['!'] = 278; $cw['"'] = 355; $cw['#'] = 556; $cw['$'] = 556; $cw['%'] = 889; $cw['&'] = 667; $cw['\''] = 191;
		$cw['('] = 333; $cw[')'] = 333; $cw['*'] = 389; $cw['+'] = 584; $cw[','] = 278; $cw['-'] = 333; $cw['.'] = 278; $cw['/'] = 278;
		for($i=48;$i<=57;$i++) $cw[chr($i)] = 556; // numbers 0-9
		$cw[':'] = 278; $cw[';'] = 278; $cw['<'] = 584; $cw['='] = 584; $cw['>'] = 584; $cw['?'] = 556; $cw['@'] = 1015;
		for($i=65;$i<=90;$i++) $cw[chr($i)] = 667; // A-Z
		$cw['['] = 278; $cw['\\'] = 278; $cw[']'] = 278; $cw['^'] = 469; $cw['_'] = 556; $cw['`'] = 333;
		for($i=97;$i<=122;$i++) $cw[chr($i)] = 556; // a-z
		$cw['{'] = 333; $cw['|'] = 260; $cw['}'] = 333; $cw['~'] = 584;
		
		$name = 'Helvetica';
		if($family=='helvetica')
		{
			if($style=='B') $name = 'Helvetica-Bold';
			elseif($style=='I') $name = 'Helvetica-Oblique';
			elseif($style=='BI') $name = 'Helvetica-BoldOblique';
			else $name = 'Helvetica';
		}
		elseif($family=='times')
		{
			if($style=='B') $name = 'Times-Bold';
			elseif($style=='I') $name = 'Times-Italic';
			elseif($style=='BI') $name = 'Times-BoldItalic';
			else $name = 'Times-Roman';
		}
		elseif($family=='courier')
		{
			if($style=='B') $name = 'Courier-Bold';
			elseif($style=='I') $name = 'Courier-Oblique';
			elseif($style=='BI') $name = 'Courier-BoldOblique';
			else $name = 'Courier';
		}

		$this->fonts[$fontkey] = array('i'=>count($this->fonts)+1, 'type'=>'core', 'name'=>$name, 'up'=>-100, 'ut'=>50, 'cw'=>$cw);
	}

	protected function _escape($s)
	{
		$s = str_replace('\\','\\\\',$s);
		$s = str_replace('(','\\(',$s);
		$s = str_replace(')','\\)',$s);
		$s = str_replace("\r",'\\r',$s);
		return $s;
	}

	protected function _dounderline($x, $y, $txt)
	{
		$up = $this->CurrentFont['up'];
		$ut = $this->CurrentFont['ut'];
		$w = $this->GetStringWidth($txt)+$this->ws*substr_count($txt,' ');
		return sprintf('%.2F %.2F %.2F %.2F re f',$x*$this->k,($this->h-($y-$up/1000*$this->FontSize))*$this->k,$w*$this->k,-$ut/1000*$this->FontSizePt);
	}

	protected function _out($s)
	{
		if($this->state==2)
			$this->pages[$this->page] .= $s."\n";
		elseif($this->state==1)
			$this->_put($s);
		elseif($this->state==0)
			$this->Error('No page has been added yet');
		elseif($this->state==3)
			$this->Error('The document is closed');
	}

	protected function _put($s)
	{
		$this->buffer .= $s."\n";
	}

	protected function _getoffset()
	{
		return strlen($this->buffer);
	}

	protected function _newobj($n=null)
	{
		if($n===null)
			$n = ++$this->n;
		$this->offsets[$n] = $this->_getoffset();
		$this->_put($n.' 0 obj');
	}

	protected function _putstream($data)
	{
		$this->_put('stream');
		$this->_put($data);
		$this->_put('endstream');
	}

	protected function _putstreamobject($data)
	{
		if($this->compress)
		{
			$entries = '/Filter /FlateDecode ';
			$data = gzcompress($data);
		}
		else
			$entries = '';
		$entries .= '/Length '.strlen($data);
		$this->_newobj();
		$this->_put('<<'.$entries.'>>');
		$this->_putstream($data);
		$this->_put('endobj');
	}

	protected function _pageentry()
	{
		$this->_newobj();
		$this->_put('<</Type /Page');
		$this->_put('/Parent 1 0 R');
		if(isset($this->PageInfo[$this->page]['size']))
			$this->_put(sprintf('/MediaBox [0 0 %.2F %.2F]',$this->PageInfo[$this->page]['size'][0],$this->PageInfo[$this->page]['size'][1]));
		if(isset($this->PageInfo[$this->page]['rotation']))
			$this->_put('/Rotate '.$this->PageInfo[$this->page]['rotation']);
		$this->_put('/Resources 2 0 R');
		if(isset($this->PageLinks[$this->page]))
		{
			$annots = '/Annots [';
			foreach($this->PageLinks[$this->page] as $pl)
			{
				$rect = sprintf('%.2F %.2F %.2F %.2F',$pl[0],$pl[1],$pl[0]+$pl[2],$pl[1]-$pl[3]);
				$annots .= '<</Type /Annot /Subtype /Link /Rect ['.$rect.'] /Border [0 0 0] ';
				if(is_string($pl[4]))
					$annots .= '/A <</S /URI /URI '.$this->_textstring($pl[4]).'>>>>';
				else
				{
					$l = $this->links[$pl[4]];
					$h = isset($this->PageInfo[$l[0]]['size']) ? $this->PageInfo[$l[0]]['size'][1] : $this->hPt;
					$annots .= sprintf('/Dest [%d 0 R /XYZ 0 %.2F null]>>',1+2*$l[0],$h-$l[1]*$this->k);
				}
			}
			$this->_put($annots.']');
		}
		$this->_put('/Contents '.($this->n+1).' 0 R>>');
		$this->_put('endobj');
	}

	protected function _enddoc()
	{
		$this->state = 1;
		$nb = $this->page;
		if($this->AliasNbPages)
		{
			for($n=1;$n<=$nb;$n++)
				$this->pages[$n] = str_replace($this->AliasNbPages,$nb,$this->pages[$n]);
		}
		for($n=1;$n<=$nb;$n++)
			$this->_putstreamobject($this->pages[$n]);
		$this->_putresources();
		$this->_newobj(1);
		$this->_put('<</Type /Pages');
		$kids = '/Kids [';
		for($n=1;$n<=$nb;$n++)
			$kids .= (2+2*$n-1).' 0 R ';
		$this->_put($kids.']');
		$this->_put('/Count '.$nb);
		$this->_put(sprintf('/MediaBox [0 0 %.2F %.2F]',$this->DefPageSize[0]*$this->k,$this->DefPageSize[1]*$this->k));
		$this->_put('>>');
		$this->_put('endobj');
		$this->_putinfo();
		$this->_putcatalog();
		$o = $this->_getoffset();
		$this->_put('xref');
		$this->_put('0 '.($this->n+1));
		$this->_put('0000000000 65535 f ');
		for($i=1;$i<=$this->n;$i++)
			$this->_put(sprintf('%010d 00000 n ',$this->offsets[$i]));
		$this->_put('trailer');
		$this->_put('<</Size '.($this->n+1));
		$this->_put('/Root '.$this->n.' 0 R');
		$this->_put('/Info '.($this->n-1).' 0 R');
		$this->_put('>>');
		$this->_put('startxref');
		$this->_put($o);
		$this->_put('%%EOF');
		$this->state = 3;
	}

	protected function _putresources()
	{
		$this->_putfonts();
		$this->_newobj(2);
		$this->_put('<<');
		$this->_put('/ProcSet [/PDF /Text /ImageB /ImageC /ImageI]');
		$this->_put('/Font <<');
		foreach($this->fonts as $font)
			$this->_put('/F'.$font['i'].' '.$font['n'].' 0 R');
		$this->_put('>>');
		$this->_put('>>');
		$this->_put('endobj');
	}

	protected function _putfonts()
	{
		foreach($this->fonts as $k=>$font)
		{
			$this->_newobj();
			$this->fonts[$k]['n'] = $this->n;
			$this->_put('<</Type /Font');
			$this->_put('/BaseFont /'.$font['name']);
			$this->_put('/Subtype /Type1');
			if($font['name']!='Symbol' && $font['name']!='ZapfDingbats')
				$this->_put('/Encoding /WinAnsiEncoding');
			$this->_put('>>');
			$this->_put('endobj');
		}
	}

	protected function _putinfo()
	{
		$this->_newobj();
		$this->_put('<<');
		$this->_put('/Producer '.$this->_textstring('FPDF '.FPDF_VERSION));
		if(!empty($this->metadata['Title']))
			$this->_put('/Title '.$this->_textstring($this->metadata['Title']));
		if(!empty($this->metadata['Subject']))
			$this->_put('/Subject '.$this->_textstring($this->metadata['Subject']));
		if(!empty($this->metadata['Author']))
			$this->_put('/Author '.$this->_textstring($this->metadata['Author']));
		if(!empty($this->metadata['Keywords']))
			$this->_put('/Keywords '.$this->_textstring($this->metadata['Keywords']));
		if(!empty($this->metadata['Creator']))
			$this->_put('/Creator '.$this->_textstring($this->metadata['Creator']));
		$this->_put('/CreationDate '.$this->_textstring('D:'.@date('YmdHis')));
		$this->_put('>>');
		$this->_put('endobj');
	}

	protected function _putcatalog()
	{
		$n1 = 1;
		$this->_newobj();
		$this->_put('<</Type /Catalog');
		$this->_put('/Pages '.$n1.' 0 R');
		if($this->ZoomMode=='fullpage')
			$this->_put('/OpenAction [3 0 R /Fit]');
		elseif($this->ZoomMode=='fullwidth')
			$this->_put('/OpenAction [3 0 R /FitH null]');
		elseif($this->ZoomMode=='real')
			$this->_put('/OpenAction [3 0 R /XYZ null null 1]');
		elseif(!is_string($this->ZoomMode))
			$this->_put('/OpenAction [3 0 R /XYZ null null '.sprintf('%.2F',$this->ZoomMode/100).']');
		if($this->LayoutMode=='single')
			$this->_put('/PageLayout /SinglePage');
		elseif($this->LayoutMode=='continuous')
			$this->_put('/PageLayout /OneColumn');
		elseif($this->LayoutMode=='two')
			$this->_put('/PageLayout /TwoColumnLeft');
		$this->_put('>>');
		$this->_put('endobj');
	}

	protected function _textstring($s)
	{
		if(!$this->_isascii($s))
			$s = $this->_UTF8toUTF16($s);
		return '('.$this->_escape($s).')';
	}

	protected function _isascii($s)
	{
		$nb = strlen($s);
		for($i=0;$i<$nb;$i++)
		{
			if(ord($s[$i])>127)
				return false;
		}
		return true;
	}

	protected function _UTF8toUTF16($s)
	{
		$res = "\xFE\xFF";
		$nb = strlen($s);
		$i = 0;
		while($i<$nb)
		{
			$c1 = ord($s[$i++]);
			if($c1>=224)
			{
				$c2 = ord($s[$i++]);
				$c3 = ord($s[$i++]);
				$res .= chr((($c1 & 0x0F)<<4) + (($c2 & 0x3C)>>2));
				$res .= chr((($c2 & 0x03)<<6) + ($c3 & 0x3F));
			}
			elseif($c1>=192)
			{
				$c2 = ord($s[$i++]);
				$res .= chr(($c1 & 0x1C)>>2);
				$res .= chr((($c1 & 0x03)<<6) + ($c2 & 0x3F));
			}
			else
			{
				$res .= "\0".chr($c1);
			}
		}
		return $res;
	}

	protected function _UTF8encode($s)
	{
		return $s;
	}

	protected function _httpencode($param, $value, $isUTF8)
	{
		if($this->_isascii($value))
			return $param.'="'.$value.'"';
		return $param.'*="'.rawurlencode($value).'"';
	}
}
