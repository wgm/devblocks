<?php
/**
 *
 * Require Figlet class for rendering the text.
 *
 */
require_once 'Text/Figlet.php';


/**
 * Text_CAPTCHA_Driver_Figlet - Text_CAPTCHA driver Figlet based CAPTCHAs
 *
 * @license PHP License, version 3.0
 * @author Aaron Wormus <wormus@php.net>
 * @author Christian Wenz <wenz@php.net>
 * @todo define an obfuscation algorithm 
 */

class Text_CAPTCHA_Driver_Figlet extends Text_CAPTCHA
{
    /**
     * Text_Figlet object
     *
     * @access private
     * @var resource
     */
    var $_fig;

    /**
     * Width of CAPTCHA
     *
     * @access private
     * @var int
     */
    var $_width;

    /**
     * Figlet output string
     *
     * @access private
     * @var string
     */
    var $_output_string;

     /**
     * Figlet font options
     *
     * @access private
     * @var array
     */
    var $_fonts = array();

    /**
     * Figlet font
     *
     * @access private
     * @var string
     */
    var $_font;
   
    /**
     * Figlet font
     *
     * @access private
     * @var array
     */
    var $_style = array();
    
    /**
     * Output Format
     *
     * @access private
     * @var string
     */
    var $_output;

    /**
     * Last error
     *
     * @access protected
     * @var PEAR_Error
     */
    var $_error = null;

    /**
     * init function
     *
     * Initializes the new Text_CAPTCHA_Driver_Figlet object and creates a GD image
     *
     * @param   array   $options    CAPTCHA options
     * @access public
     * @return  mixed   true upon success, PEAR error otherwise
     */
    function init($options = array())
    {
        if (is_array($options)) {
            if (!empty($options['output'])){
              $this->_output = $options['output'];
            } else {
              $this->_output = 'html';
            }
         
            if (isset($options['width']) && is_int($options['width'])) {
              $this->_width = $options['width'];
            } else {
              $this->_width = 200; 
            }

            if (!empty($options['length'])){
                $this->_length = $options['length'];
            } else {
                $this->_length = 6;
            }
            
            if (!isset($options['phrase']) || empty($options['phrase'])) {
                $this->_createPhrase($this->_length);
            } else {
                $this->_phrase = $options['phrase'];
            }
        }
        
        if (empty($options['options']) || !is_array($options['options'])){
            die;
        } else {
            if (!empty($options['options']['style']) && is_array($options['options']['style'])){
                $this->_style = $options['options']['style'];
            }
            
            if (empty($this->style['padding'])){
                $this->_style['padding'] = '5px';    
            }
            
            if (!empty($options['options']['font_file'])){
                if (is_array($options['options']['font_file'])){
                    $this->_font = $options['options']['font_file'][array_rand($options['options']['font_file'])];
                } else {
                    $this->_font = $options['options']['font_file'];
                }
            }
        }
    }

    /**
     * Create random CAPTCHA phrase
     * This method creates a random phrase
     *
     * @access  private
     */
    function _createPhrase()
    {
        $this->_phrase = Text_Password::create($this->_length);
    }

    /**
     * Create CAPTCHA image
     *
     * This method creates a CAPTCHA image
     *
     * @access  private
     * @return  void   PEAR_Error on error
     */
    function _createCAPTCHA()

    {
        $this->_fig = new Text_Figlet();
        
        if (PEAR::isError($this->_fig->LoadFont($this->_font))){
            $this->_error = PEAR::raiseError('Error loading Text_Figlet font');
            return $this->_error;
        }

	      $this->_output_string = $this->_fig->LineEcho($this->_phrase);        
    }

    /**
     * Return CAPTCHA in the specified format
     *
     * This method returns the CAPTCHA depending on the output format
     *
     * @access  public
     * @return  mixed        Formatted captcha or PEAR error
     */
    function getCAPTCHA()
    {
        $retval = $this->_createCAPTCHA();
        if (PEAR::isError($retval)) {
            return PEAR::raiseError($retval->getMessage());
        }

        switch ($this->_output) {
            case 'text':
                return $this->_output_string;
                break;
            case 'html':
                return $this->getCAPTCHAAsHTML();
                break; 
            case 'javascript':
                return $this->getCAPTCHAAsJavascript();
                break;
        }
    }

    /**
     * Return CAPTCHA as HTML
     *
     * This method returns the CAPTCHA as HTML
     *
     * @access  public
     * @return  mixed        HTML Figlet image or PEAR error
     */
    function getCAPTCHAAsHTML()
    {
        $retval = $this->_createCAPTCHA();
        if (PEAR::isError($retval)) {
            return PEAR::raiseError($retval->getMessage());
        }
        
        $charwidth = strpos($this->_output_string, "\n");
        $data = str_replace("\n", '<br />', $this->_output_string);

        $textsize = ($this->_width / $charwidth) * 1.4;
        
        $css_output = "";
        foreach ($this->_style as $key => $value){
            $css_output .= "$key: $value;"; 
        }
        
        $htmloutput = '<div style="font-family: courier; 
          font-size: '.$textsize.'px; 
          width:'.$this->_width.'px; 
          text-align:center;">';
        $htmloutput .= '<div style="'.$css_output.'margin:0px;">
          <pre style="padding: 0px; margin: 0px;">'. $data. '</pre></div></div>';

        return $htmloutput; 
    }

    /**
     * Return CAPTCHA as Javascript version of HTML
     *
     * This method returns the CAPTCHA as a Javascript string
     * I'm not exactly sure what the point of doing this would be.
     *
     * @access  public
     * @return  mixed        javascript string or PEAR error
     */
    function getCAPTCHAAsJavascript()
    {
        $data = $this->getCAPTCHAAsHTML();
        if (PEAR::isError($data)) {
            return PEAR::raiseError($data->getMessage());
        }
        
        $obfus_data = rawurlencode($data);
        
        $javascript = "<script language=\"javascript\">
          document.write(unescape(\"$obfus_data.\" ) );
          </script>";
        
        return $javascript;
    }
}
