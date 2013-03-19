<?php

/**
 * Класс для работы с изображениями. View. Transform. Upload.
 * Убедитесь, что библитека GD подключена (phpinfo())
 * 
 * Пример работы:
 *  $image = ImageClass::setObject($_FILES['image'])
 *                  ->grayscale()
 *                  ->resize(350, 350)
 *                  ->crop(100, 100)
 *                  ->save('img/lessons')
 * 
 *  print $image->view(); 
 * 
 */


class ImageClass
{

	public $uploaded = array();	
	
	public $error    = false;	

    // Хэлперы
    protected static function is_empty($s)
    {
        return strlen(trim($s)) ? false : true;
    }

    // Возвращает расширение файла если оно есть, на вход имя файла или полный путь
    protected static function get_file_extension($name, $default = "")
    {
        return preg_match("/^.*(\.[a-zA-Z0-9_-]+)$/s", $name, $regs) ? strtolower($regs[1]) : $default;
    }

    protected static function generateUniqueId()
    {
        return md5( mt_rand(0, 1000000) . time() . uniqid( '_isuid', true) );
    }


    /**
     * Полная информация о файле
     * @param   array  $_FILES или(!) Путь до изображения, если файл локальный. Также можно передавать и любые другие файлы
	 * @example $uploaded = ImageClass::set($_FILES['index']);
	 * @example $uploaded = ImageClass::set('img/photo.jpg');
     */

    static function set($file)
    {
       
		if(is_array($file))
		{	
			
			$uploaded["size"]   = @$file["size"];
			//file - это полный путь к файлу, включая его имя
			$uploaded["file"]   = @$file["tmp_name"];	
            $uploaded["name"]   = $file["name"];
           	
		}else{ 
			
			$uploaded["file"]   = $file;
			$uploaded["name"]   = @basename($file);		
		}
		
		
		$uploaded["unicname"]  = self::generateUniqueId();
		
		$uploaded["ext"]  = strtolower(pathinfo($uploaded["name"], PATHINFO_EXTENSION));

		$uploaded["data"] = @file_get_contents($uploaded["file"]);

		if( $uploaded["data"] == false ) { $uploaded['error']  = 'failed to open stream: '.$uploaded["file"]; return $uploaded; }

		//Если это картинка!
		if(in_array($uploaded["ext"],array('gif','png','jpeg','jpg')))
		{	
			$image =  @getimagesize($uploaded["file"]);
		
			if( $image == false ) { $uploaded['error']  = 'failed to open stream: '.$uploaded["file"]; return $uploaded; }
			
			
			
			
			$uploaded["width"]  = $image[0];
			$uploaded["height"] = $image[1];
			$uploaded["type"]   = $image[2];
			$uploaded["mime"]   = image_type_to_mime_type($uploaded["type"]);			
		}

        return $uploaded;
    }

    /**
     * Тоже самое что и выше, но в форме объекта
     * @param   array $_FILES
     * @example $image = ImageClass::setObject($_FILES['index']);
     */


    static function setObject($file)
    {
        $object   = new ImageClass();

        $uploaded = self::set($file);

        foreach ($uploaded as $key=>$_uploaded)
        {
            $object->$key = $_uploaded;
        }
		
		if( !empty($uploaded['error'])  ) {

			$object->error = $uploaded['error'];
	
		}else{

			if(isset($object->type))
			{		
				switch ($object->type)
				{
					case IMAGETYPE_JPEG:
						$create = 'imagecreatefromjpeg';
						break;
					case IMAGETYPE_GIF:
						$create = 'imagecreatefromgif';
						break;
					case IMAGETYPE_PNG:
						$create = 'imagecreatefrompng';
						break;
				}
				
				// Save function for future use
				$object->_create_function = $create;
					
			}
			
			// Save filename for lazy loading
			$object->_image   = $object->file;	
		}

        return $object;
    } 
  


    /**
     *  Аплоад файла. На вход - путь до папки, куда надо сделать выгрузку
     *  Возвращает Имя файла с расширением или ошибку!
     *
     *  @example 
     *  $image = ImageClass::setObject($_FILES['index'])->save('img/lessons','filename',80);
     */

    function save($path,$unicname = false,$quality = 100)
    {
		
		if( $this->error != false ) return $this;


        if( @!is_dir($path) ) 
        {
            if( @!mkdir($path) )
            {
                $this->error = 'Не удается создать директорию '.$path.' Возможно дело в слешах /';
            }

        } else if( @!is_writable($path) )
        {
            $this->error = 'Папка '.$path.' не доступна для записи';
        }

        if(!$this->error)
        {

            //Если не передаем название файла - юзать уникальный 
			$filename  = ($unicname) ? $unicname : self::generateUniqueId();

			$this->pathToSave = $path . '/' . $filename.'.'.$this->ext;
	
            // Если задействован GD      
            if(is_resource($this->_image))
            {
            				      
					// Get the extension of the file
					$extension = pathinfo($this->pathToSave, PATHINFO_EXTENSION);

					// Get the save function and IMAGETYPE
					list($save, $type) = $this->_save_function($extension, $quality);

					// Save the image to a file
					$status = isset($quality) ? $save($this->_image, $this->pathToSave, $quality) : $save($this->_image, $this->pathToSave);

					if ($status === TRUE AND $type !== $this->type)
					{
						// Reset the image type and mime type
						$this->type = $type;
						$this->mime = image_type_to_mime_type($type);					
					}

            }else{

					$fp = fopen($this->pathToSave, 'wb');

					if( $fp )
					{	
						fwrite($fp, $this->data);			
						fclose($fp);
						//$this->name  = $fullFileName;

					}else
					{
						$this->error = 'Не удается записать файл'.$this->pathToSave;
					}										
            }
			
	        $this->name = $filename;
        }
		
				


        return $this;

    }

     /**
     * Render картинки. Возвращет data файла, в строку
     *
     *     // Render the image at 50% quality
     *     $data = $image->render(NULL, 50);
     *
     *     // Render the image as a PNG
     *     $data = $image->render('png');
     *
     * @param   string   image type to return: png, jpg, gif, etc
     * @param   integer  quality of image: 1-100
     * @return  string
     * @uses    ImageClass::_do_render
     *
     * Просмотреть после рендера можно так:
     * header('Content-type: ' . $data->type);
       exit($data->render());
     *
     *
     */
 
    public function render($type = NULL, $quality = 100)
    {
		
        if ($type === NULL)
        {
            // Use the current image type
            $type = image_type_to_extension($this->type, FALSE);
        }

        return $this->_do_render($type, $quality);
    } 
    
	/** 
	 *  View. Просмотр data файла.
	 * 
	 */
	
    public function view()
    {
        $image = $this->render();

		$type = image_type_to_extension($this->type, FALSE);
		      
        header('Content-type: ' . $type);
        exit($image);
    }
	
	/**
	 *  Download. Скачивание файла.
	 * 
	 */
	
	public function download()
    {
        //$image = $this->render();
		
		//Если не скачивается - дело в переднем слеше
		header('Content-disposition: attachment; filename="'.$this->name.'"');
        header('Content-type: "application/octet-stream"');
		
        exit($this->data);
    }
	
    
    #####################################
    ####### IMAGE EDIT ##################
    #####################################

    // Resizing contraints
    const NONE    = 0x01;
    const WIDTH   = 0x02;
    const HEIGHT  = 0x03;
    const AUTO    = 0x04;
    const INVERSE = 0x05;

    // Flipping directions
    const HORIZONTAL = 0x11;
    const VERTICAL   = 0x12;


    /** 
     *  Черно белый фильтр [http://de.php.net/manual/en/function.imagefilter.php]
     *
     */
      
    public function grayscale()
    {

    /*  //Как вариант можно использовать и этот закомментированный кусок, совместно с функцией yiq()

        // Creating the Canvas
        $bwimage = $this->_create($this->width, $this->height);

       if( $this->error) return $this; $this->_load_image(); //Loads image if not yet loaded 

        //Creates the 256 color palette
        $palette = array();

        for ($c = 0; $c < 256; $c++)
        {
          $palette[$c] = imagecolorallocate($bwimage, $c, $c, $c);
        }

        //Reads the original colors pixel by pixel
        for ($y = 0; $y < $this->height; $y++)
        {
            for ($x = 0; $x < $this->width; $x++)
            {
                $rgb = imagecolorat($this->_image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                // This is where we actually use yiq to modify our rgb values, and then convert them to our grayscale palette
                $gs = $this->yiq($r, $g, $b);

                imagesetpixel($bwimage, $x, $y, $palette[$gs]);
            }
        }

        imagecopy($this->_image, $bwimage, 0, 0, 0, 0, $this->width, $this->height);

        imagedestroy($this->_image);

        $this->_image = $bwimage;

        return $this;

    */



        
        if( $this->error) return $this; 
	   
	    $this->_load_image(); //Loads image if not yet loaded 
    
        imagefilter($this->_image,IMG_FILTER_GRAYSCALE);

        //$this->width  = imagesx($this->_image);
        //$this->height = imagesy($this->_image);

        return $this;





    }
    
    protected function yiq($r, $g, $b)
    {
        return (($r * 0.299) + ($g * 0.587) + ($b * 0.114));
    }


	/**
		Текст на картинке [http://php.net/manual/ru/function.imagefttext.php]
		Example:
		ImageClass::setObject('data/original/17395614.jpg')
		->text('ХУЙ ЗАЛУПА',50,105,55,'#0F67A1')
		->save('data');
	*/
	public function text($text,$fontSize=false,$x=false,$y=false,$fontColor=false,$font_file=false,$fontAngle=false)
    {
		 
		
        if( $this->error) return $this;

		$this->_load_image(); //Loads image if not yet loaded
		
		$fontSize = ($fontSize != false ) ? $fontSize : 13; // В POINTS!
				
		$x = ($x != false) ? $x : 105;
		$y = ($y != false) ? $y : 55;
		
		if($fontColor!=false)
		{
			$colorArray = sscanf($fontColor, '#%2x%2x%2x');
			
		}else{
		
			// black
			$colorArray[0] = 0x00;
			$colorArray[1] = 0x00;
			$colorArray[2] = 0x00;		
		}
				
		$fontColor = imagecolorallocate($this->_image, $colorArray[0], $colorArray[1], $colorArray[2]); 
		
		$fontAngle = ($fontAngle != false) ? $fontAngle : 0; // Угол наклона
		
		// background
		//$backgroundColor = imagecolorallocate($this->_image, 0xFF, 0x00, 0x00);
		//imagefilledrectangle($this->_image, 0, 0, 299, 99, $backgroundColor);
		
		// Path to our ttf font file
		$font_file = ($font_file!= false) ? $font_file : 'data/arial.ttf';
		
		imagefttext($this->_image, $fontSize, $fontAngle, $x, $y, $fontColor, $font_file, $text);
		
		return $this;
	}
	



    /**
     * Resize the image to the given size. Either the width or the height can
     * be omitted and the image will be resized proportionally.
     *
     *     // Resize to 200 pixels on the shortest side
     *     $image->resize(200, 200);
     *
     *     // Resize to 200x200 pixels, keeping aspect ratio
     *     $image->resize(200, 200, ImageClass::INVERSE);
     *
     *     // Resize to 500 pixel width, keeping aspect ratio
     *     $image->resize(500, NULL);
     *
     *     // Resize to 500 pixel height, keeping aspect ratio
     *     $image->resize(NULL, 500);
     *
     *     // Resize to 200x500 pixels, ignoring aspect ratio
     *     $image->resize(200, 500, ImageClass::NONE);
     *
     * @param   integer  new width
     * @param   integer  new height
     * @param   integer  master dimension
     * @return  $this
     * @uses    ImageClass::_do_resize
     */
    public function resize($width = NULL, $height = NULL, $master = NULL)
    {
	
        if ($master === NULL)
        {
            // Choose the master dimension automatically
            $master = ImageClass::AUTO;
        }
        // ImageClass::WIDTH and ImageClass::HEIGHT depricated. You can use it in old projects,
        // but in new you must pass empty value for non-master dimension
        elseif ($master == ImageClass::WIDTH AND ! empty($width))
        {
            $master = ImageClass::AUTO;

            // Set empty height for backvard compatibility
            $height = NULL;
        }
        elseif ($master == ImageClass::HEIGHT AND ! empty($height))
        {
            $master = ImageClass::AUTO;

            // Set empty width for backvard compatibility
            $width = NULL;
        }

        if (empty($width))
        {
            if ($master === ImageClass::NONE)
            {
                // Use the current width
                $width = $this->width;
            }
            else
            {
                // If width not set, master will be height
                $master = ImageClass::HEIGHT;
            }
        }

        if (empty($height))
        {
            if ($master === ImageClass::NONE)
            {
                // Use the current height
                $height = $this->height;
            }
            else
            {
                // If height not set, master will be width
                $master = ImageClass::WIDTH;
            }
        }

        switch ($master)
        {
            case ImageClass::AUTO:
            // Choose direction with the greatest reduction ratio
                $master = ($this->width / $width) > ($this->height / $height) ? ImageClass::WIDTH : ImageClass::HEIGHT;
                break;
            case ImageClass::INVERSE:
            // Choose direction with the minimum reduction ratio
                $master = ($this->width / $width) > ($this->height / $height) ? ImageClass::HEIGHT : ImageClass::WIDTH;
                break;
        }

        switch ($master)
        {
            case ImageClass::WIDTH:
            // Recalculate the height based on the width proportions
                $height = $this->height * $width / $this->width;
                break;
            case ImageClass::HEIGHT:
            // Recalculate the width based on the height proportions
                $width = $this->width * $height / $this->height;
                break;
        }

        // Convert the width and height to integers
        $width  = round($width);
        $height = round($height);

        $this->_do_resize($width, $height);

        return $this;
    }
	
	
	/* 
		Кропает квадратом. $ratio_size - ширина и высота кропа
		$image->square(100); 
		
	*/	
	public function square($ratio_size)
    {
		// vertical image
		if($this->height > $this->width)
		{
		
			$this->resize($ratio_size, NULL);
			$this->crop($ratio_size,$ratio_size, NULL, ($this->height - $ratio_size)/2);
	
		}
		
		// horizontal image
		if($this->width > $this->height)
		{
	
			$this->resize(NULL, $ratio_size);
			$this->crop($ratio_size,$ratio_size, ($this->width - $ratio_size)/2, NULL);
	
		}
		
		// square image
		if($this->width == $this->height)
		{	
			$this->resize($ratio_size, $ratio_size);
		}
		
		
		return $this;
	}
	


    /**
     * Crop an image to the given size. Either the width or the height can be
     * omitted and the current width or height will be used.
     *
     * If no offset is specified, the center of the axis will be used.
     * If an offset of TRUE is specified, the bottom of the axis will be used.
     *
     *     // Crop the image to 200x200 pixels, from the center
     *     $image->crop(200, 200);
     *
     * @param   integer  new width
     * @param   integer  new height
     * @param   mixed    offset from the left
     * @param   mixed    offset from the top
     * @return  $this
     * @uses    ImageClass::_do_crop
     */
    public function crop($width, $height, $offset_x = NULL, $offset_y = NULL)
    {
            if ($width > $this->width)
            {
                    // Use the current width
                    $width = $this->width;
            }

            if ($height > $this->height)
            {
                    // Use the current height
                    $height = $this->height;
            }

            if ($offset_x === NULL)
            {
                    // Center the X offset
                    $offset_x = round(($this->width - $width) / 2);
            }
            elseif ($offset_x === TRUE)
            {
                    // Bottom the X offset
                    $offset_x = $this->width - $width;
            }
            elseif ($offset_x < 0)
            {
                    // Set the X offset from the right
                    $offset_x = $this->width - $width + $offset_x;
            }

            if ($offset_y === NULL)
            {
                    // Center the Y offset
                    $offset_y = round(($this->height - $height) / 2);
            }
            elseif ($offset_y === TRUE)
            {
                    // Bottom the Y offset
                    $offset_y = $this->height - $height;
            }
            elseif ($offset_y < 0)
            {
                    // Set the Y offset from the bottom
                    $offset_y = $this->height - $height + $offset_y;
            }

            // Determine the maximum possible width and height
            $max_width  = $this->width  - $offset_x;
            $max_height = $this->height - $offset_y;

            if ($width > $max_width)
            {
                    // Use the maximum available width
                    $width = $max_width;
            }

            if ($height > $max_height)
            {
                    // Use the maximum available height
                    $height = $max_height;
            }

            $this->_do_crop($width, $height, $offset_x, $offset_y);

            return $this;
    }


    /**
     * Rotate the image by a given amount.
     *
     *     // Rotate 45 degrees clockwise
     *     $image->rotate(45);
     *
     *     // Rotate 90% counter-clockwise
     *     $image->rotate(-90);
     *
     * @param   integer   degrees to rotate: -360-360
     * @return  $this
     * @uses    ImageClass::_do_rotate
     */
    public function rotate($degrees)
    {
            // Make the degrees an integer
            $degrees = (int) $degrees;

            if ($degrees > 180)
            {
                    do
                    {
                            // Keep subtracting full circles until the degrees have normalized
                            $degrees -= 360;
                    }
                    while($degrees > 180);
            }

            if ($degrees < -180)
            {
                    do
                    {
                            // Keep adding full circles until the degrees have normalized
                            $degrees += 360;
                    }
                    while($degrees < -180);
            }

            $this->_do_rotate($degrees);

            return $this;
    }

    /**
     * Flip the image along the horizontal or vertical axis.
     *
     *     // Flip the image from top to bottom
     *     $image->flip(ImageClass::HORIZONTAL);
     *
     *     // Flip the image from left to right
     *     $image->flip(ImageClass::VERTICAL);
     *
     * @param   integer  direction: ImageClass::HORIZONTAL, ImageClass::VERTICAL
     * @return  $this
     * @uses    ImageClass::_do_flip
     */
    public function flip($direction)
    {
            if ($direction !== ImageClass::HORIZONTAL)
            {
                    // Flip vertically
                    $direction = ImageClass::VERTICAL;
            }

            $this->_do_flip($direction);

            return $this;
    }

    /**
     * Sharpen the image by a given amount.
     *
     *     // Sharpen the image by 20%
     *     $image->sharpen(20);
     *
     * @param   integer  amount to sharpen: 1-100
     * @return  $this
     * @uses    ImageClass::_do_sharpen
     */
    public function sharpen($amount)
    {
            // The amount must be in the range of 1 to 100
            $amount = min(max($amount, 1), 100);

            $this->_do_sharpen($amount);

            return $this;
    }

    /**
     * Add a reflection to an image. The most opaque part of the reflection
     * will be equal to the opacity setting and fade out to full transparent.
     * Alpha transparency is preserved.
     *
     *     // Create a 50 pixel reflection that fades from 0-100% opacity
     *     $image->reflection(50);
     *
     *     // Create a 50 pixel reflection that fades from 100-0% opacity
     *     $image->reflection(50, 100, TRUE);
     *
     *     // Create a 50 pixel reflection that fades from 0-60% opacity
     *     $image->reflection(50, 60, TRUE);
     *
     * [!!] By default, the reflection will be go from transparent at the top
     * to opaque at the bottom.
     *
     * @param   integer   reflection height
     * @param   integer   reflection opacity: 0-100
     * @param   boolean   TRUE to fade in, FALSE to fade out
     * @return  $this
     * @uses    ImageClass::_do_reflection
     */
    public function reflection($height = NULL, $opacity = 100, $fade_in = FALSE)
    {
            if ($height === NULL OR $height > $this->height)
            {
                    // Use the current height
                    $height = $this->height;
            }

            // The opacity must be in the range of 0 to 100
            $opacity = min(max($opacity, 0), 100);

            $this->_do_reflection($height, $opacity, $fade_in);

            return $this;
    }

    /**
     * Add a watermark to an image with a specified opacity. Alpha transparency
     * will be preserved.
     *
     * If no offset is specified, the center of the axis will be used.
     * If an offset of TRUE is specified, the bottom of the axis will be used.
     *
     *     // Add a watermark to the bottom right of the image
     *     $mark = ImageClass::factory('upload/watermark.png');
     *     $image->watermark($mark, TRUE, TRUE);
     *
     * @param   object   watermark ImageClass instance
     * @param   integer  offset from the left
     * @param   integer  offset from the top
     * @param   integer  opacity of watermark: 1-100
     * @return  $this
     * @uses    ImageClass::_do_watermark
     */
    public function watermark(ImageClass $watermark, $offset_x = NULL, $offset_y = NULL, $opacity = 100)
    {
            if ($offset_x === NULL)
            {
                    // Center the X offset
                    $offset_x = round(($this->width - $watermark->width) / 2);
            }
            elseif ($offset_x === TRUE)
            {
                    // Bottom the X offset
                    $offset_x = $this->width - $watermark->width;
            }
            elseif ($offset_x < 0)
            {
                    // Set the X offset from the right
                    $offset_x = $this->width - $watermark->width + $offset_x;
            }

            if ($offset_y === NULL)
            {
                    // Center the Y offset
                    $offset_y = round(($this->height - $watermark->height) / 2);
            }
            elseif ($offset_y === TRUE)
            {
                    // Bottom the Y offset
                    $offset_y = $this->height - $watermark->height;
            }
            elseif ($offset_y < 0)
            {
                    // Set the Y offset from the bottom
                    $offset_y = $this->height - $watermark->height + $offset_y;
            }

            // The opacity must be in the range of 1 to 100
            $opacity = min(max($opacity, 1), 100);

            $this->_do_watermark($watermark, $offset_x, $offset_y, $opacity);

            return $this;
    }

    /**
     * Set the background color of an image. This is only useful for images
     * with alpha transparency.
     *
     *     // Make the image background black
     *     $image->background('#000');
     *
     *     // Make the image background black with 50% opacity
     *     $image->background('#000', 50);
     *
     * @param   string   hexadecimal color value
     * @param   integer  background opacity: 0-100
     * @return  $this
     * @uses    ImageClass::_do_background
     */
    public function background($color, $opacity = 100)
    {
            if ($color[0] === '#')
            {
                    // Remove the pound
                    $color = substr($color, 1);
            }

            if (strlen($color) === 3)
            {
                    // Convert shorthand into longhand hex notation
                    $color = preg_replace('/./', '$0$0', $color);
            }

            // Convert the hex into RGB values
            list ($r, $g, $b) = array_map('hexdec', str_split($color, 2));

            // The opacity must be in the range of 0 to 100
            $opacity = min(max($opacity, 0), 100);

            $this->_do_background($r, $g, $b, $opacity);

            return $this;
    }
















####################################################
####  GD  [http://php.net/GD ] #####################
####################################################


    /**
     * Loads an image into GD.
     *
     * @return  void
     */
    protected function _load_image()
    {
		//if( isset($this->error) && $this->error != false ) return false;

        if ( ! is_resource($this->_image))
        {
		
            // Gets create function
            $create = $this->_create_function;

            // Open the temporary image
            $this->_image = $create($this->file);

            // Preserve transparency when saving
            imagesavealpha($this->_image, TRUE);
        }
    }

    protected function _do_resize($width, $height)
    {
        // Presize width and height
        $pre_width = $this->width;
        $pre_height = $this->height;


        // Loads image if not yet loaded
        if( $this->error) return $this;

		$this->_load_image(); //Loads image if not yet loaded 

        // Test if we can do a resize without resampling to speed up the final resize
        if ($width > ($this->width / 2) AND $height > ($this->height / 2))
        {
            // The maximum reduction is 10% greater than the final size
            $reduction_width  = round($width  * 1.1);
            $reduction_height = round($height * 1.1);

            while ($pre_width / 2 > $reduction_width AND $pre_height / 2 > $reduction_height)
            {
                // Reduce the size using an O(2n) algorithm, until it reaches the maximum reduction
                $pre_width /= 2;
                $pre_height /= 2;
            }

            // Create the temporary image to copy to
            $image = $this->_create($pre_width, $pre_height);

            if (imagecopyresized($image, $this->_image, 0, 0, 0, 0, $pre_width, $pre_height, $this->width, $this->height))
            {
                // Swap the new image for the old one
                imagedestroy($this->_image);
                $this->_image = $image;
            }
        }

        // Create the temporary image to copy to
        $image = $this->_create($width, $height);

        // Execute the resize
        if (imagecopyresampled($image, $this->_image, 0, 0, 0, 0, $width, $height, $pre_width, $pre_height))
        {
            // Swap the new image for the old one
            imagedestroy($this->_image);
            $this->_image = $image;

            // Reset the width and height
            $this->width  = imagesx($image);
            $this->height = imagesy($image);

        }
    }

    protected function _do_crop($width, $height, $offset_x, $offset_y)
    {
        // Create the temporary image to copy to
        $image = $this->_create($width, $height);

        // Loads image if not yet loaded
       if( $this->error) return $this; $this->_load_image(); //Loads image if not yet loaded 

        // Execute the crop
        if (imagecopyresampled($image, $this->_image, 0, 0, $offset_x, $offset_y, $width, $height, $width, $height))
        {
            // Swap the new image for the old one
            imagedestroy($this->_image);
            $this->_image = $image;

            // Reset the width and height
            $this->width  = imagesx($image);
            $this->height = imagesy($image);
        }
    }

    protected function _do_rotate($degrees)
    {

        // Loads image if not yet loaded
        if( $this->error) return $this;

		$this->_load_image(); //Loads image if not yet loaded 

        // Transparent black will be used as the background for the uncovered region
        $transparent = imagecolorallocatealpha($this->_image, 0, 0, 0, 127);

        // Rotate, setting the transparent color
        $image = imagerotate($this->_image, 360 - $degrees, $transparent, 1);

        // Save the alpha of the rotated image
        imagesavealpha($image, TRUE);

        // Get the width and height of the rotated image
        $width  = imagesx($image);
        $height = imagesy($image);

        if (imagecopymerge($this->_image, $image, 0, 0, 0, 0, $width, $height, 100))
        {
            // Swap the new image for the old one
            imagedestroy($this->_image);
            $this->_image = $image;

            // Reset the width and height
            $this->width  = $width;
            $this->height = $height;
        }
    }

    protected function _do_flip($direction)
    {
        // Create the flipped image
        $flipped = $this->_create($this->width, $this->height);

        // Loads image if not yet loaded
        if( $this->error) return $this; 
		
		$this->_load_image(); //Loads image if not yet loaded 

        if ($direction === ImageClass::HORIZONTAL)
        {
            for ($x = 0; $x < $this->width; $x++)
            {
                // Flip each row from top to bottom
                imagecopy($flipped, $this->_image, $x, 0, $this->width - $x - 1, 0, 1, $this->height);
            }
        }
        else
        {
            for ($y = 0; $y < $this->height; $y++)
            {
                // Flip each column from left to right
                imagecopy($flipped, $this->_image, 0, $y, 0, $this->height - $y - 1, $this->width, 1);
            }
        }

        // Swap the new image for the old one
        imagedestroy($this->_image);
        $this->_image = $flipped;

        // Reset the width and height
        $this->width  = imagesx($flipped);
        $this->height = imagesy($flipped);
    }

    protected function _do_sharpen($amount)
    {

        // Loads image if not yet loaded
       if( $this->error) return $this; $this->_load_image(); //Loads image if not yet loaded 

        // Amount should be in the range of 18-10
        $amount = round(abs(-18 + ($amount * 0.08)), 2);

        // Gaussian blur matrix
        $matrix = array
                (
                array(-1,   -1,    -1),
                array(-1, $amount, -1),
                array(-1,   -1,    -1),
        );

        // Perform the sharpen
        if (imageconvolution($this->_image, $matrix, $amount - 8, 0))
        {
            // Reset the width and height
            $this->width  = imagesx($this->_image);
            $this->height = imagesy($this->_image);
        }
    }

    protected function _do_reflection($height, $opacity, $fade_in)
    {

        // Loads image if not yet loaded
        if( $this->error) return $this;

		$this->_load_image(); //Loads image if not yet loaded 

        // Convert an opacity range of 0-100 to 127-0
        $opacity = round(abs(($opacity * 127 / 100) - 127));

        if ($opacity < 127)
        {
            // Calculate the opacity stepping
            $stepping = (127 - $opacity) / $height;
        }
        else
        {
            // Avoid a "divide by zero" error
            $stepping = 127 / $height;
        }

        // Create the reflection image
        $reflection = $this->_create($this->width, $this->height + $height);

        // Copy the image to the reflection
        imagecopy($reflection, $this->_image, 0, 0, 0, 0, $this->width, $this->height);

        for ($offset = 0; $height >= $offset; $offset++)
        {
            // Read the next line down
            $src_y = $this->height - $offset - 1;

            // Place the line at the bottom of the reflection
            $dst_y = $this->height + $offset;

            if ($fade_in === TRUE)
            {
                // Start with the most transparent line first
                $dst_opacity = round($opacity + ($stepping * ($height - $offset)));
            }
            else
            {
                // Start with the most opaque line first
                $dst_opacity = round($opacity + ($stepping * $offset));
            }

            // Create a single line of the image
            $line = $this->_create($this->width, 1);

            // Copy a single line from the current image into the line
            imagecopy($line, $this->_image, 0, 0, 0, $src_y, $this->width, 1);

            // Colorize the line to add the correct alpha level
            imagefilter($line, IMG_FILTER_COLORIZE, 0, 0, 0, $dst_opacity);

            // Copy a the line into the reflection
            imagecopy($reflection, $line, 0, $dst_y, 0, 0, $this->width, 1);
        }

        // Swap the new image for the old one
        imagedestroy($this->_image);
        $this->_image = $reflection;

        // Reset the width and height
        $this->width  = imagesx($reflection);
        $this->height = imagesy($reflection);
    }

    protected function _do_watermark(ImageClass $watermark, $offset_x, $offset_y, $opacity)
    {

        // Loads image if not yet loaded
        if( $this->error) return $this;
 
	    $this->_load_image(); //Loads image if not yet loaded 

        // Create the watermark image resource
        $overlay = imagecreatefromstring($watermark->render());

        // Get the width and height of the watermark
        $width  = imagesx($overlay);
        $height = imagesy($overlay);

        if ($opacity < 100)
        {
            // Convert an opacity range of 0-100 to 127-0
            $opacity = round(abs(($opacity * 127 / 100) - 127));

            // Allocate transparent white
            $color = imagecolorallocatealpha($overlay, 255, 255, 255, $opacity);

            // The transparent image will overlay the watermark
            imagelayereffect($overlay, IMG_EFFECT_OVERLAY);

            // Fill the background with transparent white
            imagefilledrectangle($overlay, 0, 0, $width, $height, $color);
        }

        // Alpha blending must be enabled on the background!
        imagealphablending($this->_image, TRUE);

        if (imagecopy($this->_image, $overlay, $offset_x, $offset_y, 0, 0, $width, $height))
        {
            // Destroy the overlay image
            imagedestroy($overlay);
        }
    }

    protected function _do_background($r, $g, $b, $opacity)
    {
        // Loads image if not yet loaded
       if( $this->error) return $this;

	   $this->_load_image(); //Loads image if not yet loaded 

        // Convert an opacity range of 0-100 to 127-0
        $opacity = round(abs(($opacity * 127 / 100) - 127));

        // Create a new background
        $background = $this->_create($this->width, $this->height);

        // Allocate the color
        $color = imagecolorallocatealpha($background, $r, $g, $b, $opacity);

        // Fill the image with white
        imagefilledrectangle($background, 0, 0, $this->width, $this->height, $color);

        // Alpha blending must be enabled on the background!
        imagealphablending($background, TRUE);

        // Copy the image onto a white background to remove all transparency
        if (imagecopy($background, $this->_image, 0, 0, 0, 0, $this->width, $this->height))
        {
            // Swap the new image for the old one
            imagedestroy($this->_image);
            $this->_image = $background;
        }
    }


    protected function _do_render($type, $quality)
    {
        // Loads image if not yet loaded
       if( $this->error) return $this; 
	   
	   $this->_load_image(); //Loads image if not yet loaded 			

        // Get the save function and IMAGETYPE
        list($save, $type) = $this->_save_function($type, $quality);

        // Capture the output
        ob_start();

        // Render the image
        $status = isset($quality) ? $save($this->_image, NULL, $quality) : $save($this->_image, NULL);

        if ($status === TRUE AND $type !== $this->type)
        {
            // Reset the image type and mime type
            $this->type = $type;
            $this->mime = image_type_to_mime_type($type);
        }

        return ob_get_clean();
    }

    /**
     * Get the GD saving function and image type for this extension.
     * Also normalizes the quality setting
     *
     * @param   string   image type: png, jpg, etc
     * @param   integer  image quality
     * @return  array    save function, IMAGETYPE_* constant
     *
     */
    protected function _save_function($extension, & $quality)
    {
        switch (strtolower($extension))
        {

            case 'jpg':
				
            case 'jpeg':
                // Save a JPG file
                $save = 'imagejpeg';
                $type = IMAGETYPE_JPEG;
                break;
            case 'gif':
                // Save a GIF file
                $save = 'imagegif';
                $type = IMAGETYPE_GIF;

                // GIFs do not a quality setting
                $quality = NULL;
                break;
            case 'png':
                // Save a PNG file
                $save = 'imagepng';
                $type = IMAGETYPE_PNG;

                // Use a compression level of 9 (does not affect quality!)
                $quality = 9;
                break;
            default:
                exit('GD not support this type file. View error in: ImageClass::_save_function');
              
                break;
        }

        return array($save, $type);
    }

    /**
     * Create an empty image with the given width and height.
     *
     * @param   integer   image width
     * @param   integer   image height
     * @return  resource
     */
    protected function _create($width, $height)
    {
        // Create an empty image
        $image = imagecreatetruecolor($width, $height);

        // Do not apply alpha blending
        imagealphablending($image, FALSE);

         // Save alpha levels
        imagesavealpha($image, TRUE);

        return $image;
    }






}

