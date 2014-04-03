<?php

namespace Phidias\Util;

class Image
{

	/* 
	Creates a thumbnail from the specified file and save a thumbnail in a temporary location.
	Returns the filename in the temporary location
	*/
	public static function createThumbnail($image, $width, $height, $type = NULL)
	{
		if (!is_file($image)) {
			dumpx("$image not found");
			return NULL;
		}

		if ($type === NULL) {
			$pathInfo = pathinfo($image);
			$type     = strtolower($pathInfo['extension']);
		}

		$targetDirectory = Environment::realPath(Environment::DIR_TEMP.'/thumbnails');
		Filesystem::createDirectory($targetDirectory);

		$targetFile = $targetDirectory.'/'.basename($image).time().".".$type;

		return self::resizecrop($width, $height, $image, $targetFile, $type) ? $targetFile : NULL;
	}

	public static function resizecrop($forcedwidth, $forcedheight, $sourcefile, $destfile, $type = false, $imgcomp = 100, $offset_x = 0, $offset_y = 0)
	{
		if (!$type){
			$info = pathinfo($sourcefile);
			$type = strtolower($info['extension']);
		} else {
			$type = strtolower($type);
		}

		if ( $type == 'jpg' || $type == 'pjpeg' ) {
			$type = 'jpeg';
		}

		if ( !is_callable("imagecreatefrom$type") ) {
			return false;
		}

		$imagecreatefromtype = "imagecreatefrom$type";

		/* Determine the output file type based on destile's extension */
		$info		= pathinfo($destfile);
		$otype		= strtolower($info['extension']);

		if ( $otype == 'jpg' || $otype == 'pjpeg' ) {
			$otype = 'jpeg';
		}

		if ( !is_callable("image$otype") ) {
			return false;
		}

		$imagetype	= "image$otype";

		$g_imgcomp	= $imgcomp;
		$g_srcfile	= $sourcefile;
		$g_dstfile	= $destfile;
		$g_fw		= $forcedwidth;
		$g_fh		= $forcedheight;

		if ( file_exists($g_srcfile) ) {
			$g_is				= getimagesize($g_srcfile);
			$original_width		= $g_is[0];
			$original_height	= $g_is[1];

			$img_src = $imagecreatefromtype($g_srcfile);
			$img_dst = imagecreatetruecolor($g_fw,$g_fh);

			//if ( $original_width > $original_height ) {
			if ( $original_height/$original_width < $g_fh/$g_fw ) {
				$s1_height	= $g_fh;
				$s1_width	= ($s1_height*$original_width)/$original_height;
			} else {
				$s1_width	= $g_fw;
				$s1_height	= ($s1_width*$original_height)/$original_width;
			}

			/* Step 1: Resize to nearest proportion */
			imagecopyresampled($img_dst, $img_src, 0, 0, $offset_x, $offset_y, $s1_width, $s1_height, $original_width, $original_height);

			/* Step 2: Crop to final size */
			imagecopy($img_dst, $img_dst, 0, 0, 0, 0, $g_fw, $g_fh);

			/* save */
			$imagetype($img_dst, $g_dstfile, $g_imgcomp);
			imagedestroy($img_dst);
			return true;
		} else {
			return false;
		}
	}

	public static function resample($forcedwidth, $forcedheight, $sourcefile, $destfile, $type = false, $imgcomp = 100, $target_type = false)
	{
		/* Use extension as type */
		if ( !$type ) {
			$info = pathinfo($sourcefile);
			$type = $info['extension'];
		}

		if ( !$target_type ) {
			$info			= pathinfo($destfile);
			$target_type	= $info['extension'];
		}

		$type = strtolower($type);
		if ( $type == 'jpg' || $type == 'pjpeg' ) {
			$type = 'jpeg';
		}

		$target_type = strtolower($target_type);
		if ( $target_type == 'jpg' || $target_type == 'pjpeg' ) {
			$target_type = 'jpeg';
		}

		if ( !is_callable("imagecreatefrom{$type}") || !is_callable("image{$target_type}") ) {
			return false;
		}

		$imagecreatefromtype	= 'imagecreatefrom'.$type;
		$imagetype				= 'image'.$target_type;

		$g_imgcomp=$imgcomp;
		$g_srcfile=$sourcefile;
		$g_dstfile=$destfile;
		$g_fw=$forcedwidth;
		$g_fh=$forcedheight;

		if( file_exists($g_srcfile) ) {
			$g_is = getimagesize($g_srcfile);

			/*if the image already fits the cage then keep same values*/
			if ( $g_is[0] < $forcedwidth && $g_is[1] < $forcedheight ) {
				$g_iw	= $g_is[0];
				$g_ih	= $g_is[1];
				//dumpx("!!!!! ya cabe asi que las dimensiones son\n ancho: $g_iw\n alto: $g_ih");
			}else{
			    if($g_is[0] > $forcedwidth){
				    $pwidth =	$forcedwidth/($g_is[0]/100);
				    $g_ih	=	($g_is[1]/100)*$pwidth;
				    $g_iw	=	$forcedwidth;

				    if ($g_ih > $forcedheight)
				    {
					    $pheight =	$forcedheight/($g_is[1]/100);
					    $g_iw	=	($g_is[0]/100)*$pheight;
					    $g_ih	=	$forcedheight;
				    }

			    }else{
				    $pheight =$forcedheight/($g_is[1]/100);
				    $g_iw=($g_is[0]/100)*$pheight;
				    $g_ih= $forcedheight;

				    if ($g_iw > $forcedwidth)
				    {
					    $pwidth =$forcedwidth/($g_is[0]/100);
					    $g_ih=($g_is[1]/100)*$pwidth;
					    $g_iw=$forcedwidth;
				    }
			    }
			}


			/*if(($g_is[0]-$g_fw)>=($g_is[1]-$g_fh))
			{
				$g_ih=$g_fh;
				$g_iw=($g_ih/$g_is[1])*$g_is[0];
			}
			else
			{
				$g_iw=$g_fw;
				$g_ih=($g_fw/$g_is[0])*$g_is[1];
			}*/

			$img_src = $imagecreatefromtype($g_srcfile);
			$img_dst = imagecreatetruecolor($g_iw,$g_ih);
			
			/*activate correct alpha blending on png files*/
			if($target_type == 'png'){
			    imagealphablending($img_dst, false); // setting alpha blending on
			    $color = imagecolortransparent($img_dst, imagecolorallocatealpha($img_dst, 0, 0, 0, 127));
			    imagefill($img_dst, 0, 0, $color);
			    imagesavealpha($img_dst, true);
			}

			imagecopyresampled($img_dst, $img_src, 0, 0, 0, 0, $g_iw, $g_ih, $g_is[0], $g_is[1]);
			$target_type == 'png' ? $imagetype($img_dst, $g_dstfile) : $imagetype($img_dst, $g_dstfile, $g_imgcomp);
			imagedestroy($img_dst);
			return true;
		} else {
			return false;
		}
	}

}