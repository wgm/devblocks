<?php
// {{{ Class Text_CAPTCHA_Driver_Numeral
// +----------------------------------------------------------------------+
// | PHP version 5                                                       |
// +----------------------------------------------------------------------+
// | Copyright (c) 1998-2006 David Coallier                               | 
// | All rights reserved.                                                 |
// +----------------------------------------------------------------------+
// |                                                                      |
// | Redistribution and use in source and binary forms, with or without   |
// | modification, are permitted provided that the following conditions   |
// | are met:                                                             |
// |                                                                      |
// | Redistributions of source code must retain the above copyright       |
// | notice, this list of conditions and the following disclaimer.        |
// |                                                                      |
// | Redistributions in binary form must reproduce the above copyright    |
// | notice, this list of conditions and the following disclaimer in the  |
// | documentation and/or other materials provided with the distribution. |
// |                                                                      |
// | Neither the name of David Coallier nor the names of his contributors |
// | may be used to endorse                                               |
// | or promote products derived from this software without specific prior|
// | written permission.                                                  |
// |                                                                      |
// | THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS  |
// | "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT    |
// | LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS    |
// | FOR A PARTICULAR PURPOSE ARE DISCLAIMED.  IN NO EVENT SHALL THE      |
// | REGENTS OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,          |
// | INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, |
// | BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS|
// |  OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED  |
// | AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT          |
// | LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY|
// | WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE          |
// | POSSIBILITY OF SUCH DAMAGE.                                          |
// +----------------------------------------------------------------------+
// | Author: David Coallier <davidc@agoraproduction.com>                  |
// +----------------------------------------------------------------------+
//
/**
 * Class used for numeral captchas
 * 
 * This class is intended to be used to generate
 * numeral captchas as such as:
 * Example:
 *  Give me the answer to "54 + 2" to prove that you are human.
 *
 * @author   David Coallier <davidc@agoraproduction.com>
 * @author   Christian Wenz <wenz@php.net>
 * @package  Text_CAPTCHA
 * @category Text
 */
class Text_CAPTCHA_Driver_Numeral extends Text_CAPTCHA
{
    // {{{ Variables
    /**
     * Minimum range value
     * 
     * This variable holds the minimum range value 
     * default set to "1"
     * 
     * @access private
     * @var    integer $_minValue The minimum range value
     */
    var $_minValue = 1;
    
    /**
     * Maximum range value
     * 
     * This variable holds the maximum range value
     * default set to "50"
     * 
     * @access private
     * @var    integer $_maxValue The maximum value of the number range
     */
    var $_maxValue = 50;
    
    /**
     * Operators
     * 
     * The valid operators to use
     * in the numeral captcha. We could
     * use / and * but not yet.
     * 
     * @access private
     * @var    array $_operators The operations for the captcha
     */
    var $_operators = array('-', '+');
    
    /**
     * Operator to use
     * 
     * This variable is basically the operation
     * that we're going to be using in the 
     * numeral captcha we are about to generate.
     *
     * @access private
     * @var    string $_operator The operation's operator
     */
    var $_operator = '';
    
    /**
     * Mathematical Operation
     * 
     * This is the mathematical operation
     * that we are displaying to the user.
     *
     * @access private
     * @var    string $_operation The math operation
     */
    var $_operation = '';
    
    /**
     * First number of the operation
     *
     * This variable holds the first number
     * of the numeral operation we are about
     * to generate. 
     * 
     * @access private
     * @var    integer $_firstNumber The first number of the operation
     */
    var $_firstNumber = '';
    
    /**
     * Second Number of the operation
     * 
     * This variable holds the value of the
     * second variable of the operation we are
     * about to generate for the captcha.
     * 
     * @access private
     * @var    integer $_secondNumber The second number of the operation      
     */ 
    var $_secondNumber = '';
    // }}}
    // {{{ Constructor
    function init($options = array())
    {
        if (isset($options['minValue'])) {
            $this->_minValue = (int)$options['minValue'];
        }
        if (isset($options['maxValue'])) {
            $this->_maxValue = (int)$options['maxValue'];
        }
        
        $this->_createCAPTCHA();
    }
    // }}}
    // {{{ private function _createCAPTCHA
    /**
     * Create the CAPTCHA (the numeral expressio)
     * 
     * This function determines a random numeral expression
     * and set the associated class properties
     *
     * @access private
     */
    function _createCAPTCHA()
    { 
        $this->_generateFirstNumber();
        $this->_generateSecondNumber();
        $this->_generateOperator();
        $this->_generateOperation();
    }
    // }}}
    // {{{ private function _setRangeMinimum
    /**
     * Set Range Minimum value
     * 
     * This function give the developer the ability
     * to set the range minimum value so the operations
     * can be bigger, smaller, etc.
     *
     * @access private
     * @param  integer $minValue The minimum value
     */
    function _setRangeMinimum($minValue = 1) 
    {
        $this->minValue = (int)$minValue;
    }
    // }}}
    // {{{ private function _generateFirstNumber
    /**
     * Sets the first number
     * 
     * This function sets the first number 
     * of the operation by calling the _generateNumber
     * function that generates a random number.
     * 
     * @access private
     * @see    $this->_firstNumber, $this->_generateNumber
     */
    function _generateFirstNumber()
    {
        $this->_setFirstNumber($this->_generateNumber());
    }
    // }}}
    // {{{ private function generateSecondNumber
    /**
     * Sets second number
     * 
     * This function sets the second number of the
     * operation by calling _generateNumber()
     * 
     * @access private
     * @see    $this->_secondNumber, $this->_generateNumber()
     */
    function _generateSecondNumber()
    {
        $this->_setSecondNumber($this->_generateNumber());
    }
    // }}}
    // {{{ private function generateOperator
    /**
     * Sets the operation operator
     * 
     * This function sets the operation operator by
     * getting the array value of an array_rand() of
     * the $this->_operators() array.
     *
     * @access private
     * @see    $this->_operators, $this->_operator
     */
    function _generateOperator()
    {
        $this->_operator = $this->_operators[array_rand($this->_operators)];
    }
    // }}}
    // {{{ private function setAnswer
    /**
     * Sets the answer value
     * 
     * This function will accept the parameters which is
     * basically the result of the function we have done 
     * and it will set $this->answer with it.
     * 
     * @access private
     * @param  integer $phraseValue The answer value
     * @see    $this->_phrase
     */
    function _setPhrase($phraseValue)
    {   
        $this->_phrase = $phraseValue;
    }
    // }}}
    // {{{ private function setFirstNumber
    /**
     * Set First number
     *
     * This function sets the first number
     * to the value passed to the function
     *
     * @access private
     * @param  integer $value The first number value.
     */
    function _setFirstNumber($value) 
    {
        $this->_firstNumber = (int)$value;
    }
    // }}}
    // {{{ private function setSecondNumber
    /**
     * Sets the second number
     * 
     * This function sets the second number
     * with the value passed to it.
     *
     * @access private
     * @param  integer $value The second number new value.
     */
    function _setSecondNumber($value)
    {
        $this->_secondNumber = (int)$value;
    }
    // }}}
    // {{{ private function setOperation
    /**
     * Set operation
     * 
     * This variable sets the operation variable
     * by taking the firstNumber, secondNumber and operator
     *
     * @access private
     * @see    $this->_operation
     */
    function _setOperation()
    {
        $this->_operation = $this->_getFirstNumber() . ' ' .
                            $this->_operator . ' ' .
                            $this->_getSecondNumber();
    }
    // }}}
    // {{{ private function _generateNumber
    /**
     * Generate a number
     * 
     * This function takes the parameters that are in 
     * the $this->_maxValue and $this->_minValue and get
     * the random number from them using mt_rand()
     *
     * @access private
     * @return integer Random value between _minValue and _maxValue
     */
    function _generateNumber()
    {
        return mt_rand($this->_minValue, $this->_maxValue);
    }
    // }}}
    // {{{ private function _doAdd
    /**
     * Adds values
     * 
     * This function will add the firstNumber and the
     * secondNumber value and then call setAnswer to
     * set the answer value.
     * 
     * @access private
     * @see    $this->_firstNumber, $this->_secondNumber, $this->_setAnswer()
     */
    function _doAdd()
    {
        $phrase = $this->_getFirstNumber() + $this->_getSecondNumber();
        $this->_setPhrase($phrase);
    }
    // }}}
    // {{{ private function _doSubstract
    /**
     * Does a substract on the values
     * 
     * This function executes a substraction on the firstNumber
     * and the secondNumber to then call $this->setAnswer to set
     * the answer value. 
     * 
     * If the firstnumber value is smaller than the secondnumber value
     * then we regenerate the first number and regenerate the operation.
     * 
     * @access private
     * @see    $this->_firstNumber, $this->_secondNumber, $this->_setAnswer()
     */
    function _doSubstract()
    {
        $first  = $this->_getFirstNumber();
        $second = $this->_getSecondNumber();
		
        /**
         * Check if firstNumber is smaller than secondNumber
         */
    if ($first < $second) {
        $this->_setFirstNumber($second);
        $this->_setSecondNumber($first);
        $this->_setOperation();
    }

        $phrase = $this->_getFirstNumber() - $this->_getSecondNumber();
        $this->_setPhrase($phrase);
    }
    // }}}
    // {{{ private function _generateOperation
    /**
     * Generate the operation
     * 
     * This function will call the _setOperation() function
     * to set the operation string that will be called
     * to display the operation, and call the function necessary
     * depending on which operation is set by this->operator.
     * 
     * @access private
     * @see    $this->_setOperation(), $this->_operator
     */
    function _generateOperation()
    {
        $this->_setOperation();
                           
        switch ($this->_operator) {
        case '+':
            $this->_doAdd();
            break;
        case '-':
            $this->_doSubstract();
            break;
        default:
            $this->_doAdd();
            break;
        }
    }
    // }}}
    // {{{ public function _getFirstNumber
    /**
     * Get the first number
     * 
     * This function will get the first number
     * value from $this->_firstNumber
     * 
     * @access public
     * @return integer $this->_firstNumber The firstNumber
     */
    function _getFirstNumber()
    {
        return $this->_firstNumber;
    }
    // }}}
    // {{{ public function _getSecondNumber
    /**
     * Get the second number value
     * 
     * This function will return the second number value
     * 
     * @access public
     * @return integer $this->_secondNumber The second number
     */
    function _getSecondNumber()
    {
        return $this->_secondNumber;
    }
    // }}}
    // {{{ public function getCAPTCHA
    /**
     * Get operation
     * 
     * This function will get the operation
     * string from $this->_operation
     *
     * @access public
     * @return string The operation String
     */
    function getCAPTCHA()
    {
        return $this->_operation;
    }
}
// }}}
?>
