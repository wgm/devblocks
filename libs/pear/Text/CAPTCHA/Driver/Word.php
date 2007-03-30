<?php
/**
 * Text_CAPTCHA_Driver_Word - Text_CAPTCHA driver word CAPTCHAs
 *
 * Class to create a textual Turing test 
 * 
 * 
 * @license PHP License, version 3.0
 * @author Tobias Schlitt <schlitt@php.net>
 * @author Christian Wenz <wenz@php.net>
 */

/**
 *
 * Require Numbers_Words class for generating the text.
 *
 */
require_once 'Numbers/Words.php';

class Text_CAPTCHA_Driver_Word extends Text_CAPTCHA
{

    /**
     * Phrase length
     * 
     * This variable holds the length of the Word
     * 
     * @access private
     */
    var $_length;

    /**
     * Numbers_Words mode
     * 
     * This variable holds the mode for Numbers_Words
     * 
     * @access private
     */
    var $_mode;

    /**
     * Locale
     * 
     * This variable holds the locale for Numbers_Words
     * 
     * @access private
     */
    var $_locale;

    /**
     * init function
     *
     * Initializes the new Text_CAPTCHA_Driver_Word object
     *
     * @param   array   $options    CAPTCHA options with these keys:
     *                  phrase     The "secret word" of the CAPTCHA
     *                  length     The number of characters in the phrase 
     *                  locale     The locale for Numbers_Words
     *                  mode       The mode for Numbers_Words
     * @access public
     */
    function init($options = array()) 
    {
        if (isset($options['length']) && is_int($options['length'])) {
            $this->_length = $options['length'];
        } else {
            $this->_length = 4;
        }
        if (isset($options['phrase']) && !empty($options['phrase'])) {
            $this->_phrase = (string)(int)$options['phrase'];
        } else {
            $this->_createPhrase();
        }
        if (isset($options['mode']) && !empty($options['mode'])) {
            $this->_mode = $options['mode'];
        } else {
            $this->_mode = 'single';
        }
        if (isset($options['locale']) && !empty($options['locale'])) {
            $this->_locale = $options['locale'];
        } else {
            $this->_locale = 'en_US';
        }
    }

    /**
     * Create random CAPTCHA phrase, "Word edition" (numbers only)
     *
     * This method creates a random phrase
     *
     * @access  private
     */
    function _createPhrase()
    {
        $this->_phrase = (string)Text_Password::create($this->_length, 'unpronounceable', 'numeric');
    }

    /**
     * Return CAPTCHA as a string
     *
     * This method returns the CAPTCHA as string
     *
     * @access  public
     * @return  text        string
     */
    function getCAPTCHA()
    {
        $res = ''; 
        if ($this->_mode == 'single') {
            for ($i = 0; $i < strlen($this->_phrase); $i++) {
                $res .= ' '.Numbers_Words::toWords($this->_phrase{$i}, $this->_locale);
            }
        } else {
            $res = Numbers_Words::toWords($this->_phrase, $this->_locale);
        }
        return $res;
    }
}
?>
