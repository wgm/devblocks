<?php
/**
 *  Equation driver for Text_CAPTCHA.
 *  Returns simple equations as string, e.g. "9 - 2"
 *
 *  @author Christian Weiske <cweiske@php.net>
 *  @author Christian Wenz <wenz@php.net>
 */

class Text_CAPTCHA_Driver_Equation extends Text_CAPTCHA
{
    /**
     * Operators that may be used in the equation.
     * Two numbers have to be filled in, and
     *  %s is needed since number2text conversion
     *  may be applied and strings filled in.
     *
     * @access protected
     * @var array
     */
    var $_operators = array(
        '%s * %s',
        '%s + %s',
        '%s - %s',
        'min(%s, %s)',
        'max(%s, %s)'
    );

    /**
     * The equation to solve.
     *
     * @access protected
     * @var string
     */
    var $_equation = null;

    /**
     * Minimal number to use in an equation.
     *
     * @access protected
     * @var int
     */
    var $_min = 1;

    /**
     * Maximum number to use in an equation.
     *
     * @access protected
     * @var int
     */
    var $_max = 10;

    /**
     * Whether numbers shall be converted to text
     *
     * @access protected
     * @var bool
     */
    var $_numbersToText = false;

    /**
     * Complexity of the generated equations.
     * 1 - simple ones such as "1 + 10"
     * 2 - harder ones such as "(3-2)*(min(5,6))"
     *
     * @access protected
     * @var int
     */
    var $_severity = 1;

    /**
     * Last error
     *
     * @access protected
     * @var PEAR_Error
     */
    var $_error = null;


    /**
     * Initialize the driver.
     *
     * @access public
     * @return true on success, PEAR_Error on error.
     */
    function init($options = array()) {
        if (isset($options['min'])) {
            $this->_min = (int)$options['min'];
        } else {
            $this->_min = 1;
        }
        if (isset($options['max'])) {
            $this->_max = (int)$options['max'];
        } else {
            $this->_max = 10;
        }
        if (isset($options['numbersToText'])) {
            $this->_numbersToText = (bool)$options['numbersToText'];
        } else {
            $this->_numbersToText = false;
        }
        if (isset($options['severity'])) {
            $this->_severity = (int)$options['severity'];
        } else {
            $this->_severity = 1;
        }

        if ($this->_numbersToText) {
            include_once 'Numbers/Words.php';
            if (!class_exists('Numbers_Words')) {
                $this->_error = PEAR::raiseError('Number_Words package required', true);
                return $this->_error;
            }
        }

        return $this->_createPhrase();
    }

    /**
     * Create random CAPTCHA equation.
     *
     * This method creates a random equation. The equation is
     * stored in $this->_equation, the solution in $this->_phrase.
     *
     * @access protected
     * @return mixed    true on success, PEAR_Error on error
     */
    function _createPhrase()
    {
        switch ($this->_severity) {
            case 1:
                list($this->_equation, $this->_phrase) = $this->_createSimpleEquation();
                break;

            case 2:
                list($eq1, $sol1) = $this->_createSimpleEquation();
                list($eq2, $sol2) = $this->_createSimpleEquation();
                $op3 = $this->_operators[rand(0, count($this->_operators) - 1)];
                list($eq3, $this->_phrase) = $this->_solveSimpleEquation($sol1, $sol2, $op3);
                $this->_equation = sprintf($op3, '(' . $eq1 . ')', '(' . $eq2 . ')');
                break;

            default:
                $this->_error = PEAR::raiseError('Equation complexity of ' . $this->_severity . ' not supported', true);
                return $this->_error;
        }
        return true;
    }

    /**
     * Creates a simple equation of type (number operator number)
     *
     * @access protected
     * @return array    Array with equation and solution
     */
    function _createSimpleEquation()
    {
        $one = rand($this->_min, $this->_max);
        $two = rand($this->_min, $this->_max);
        $operator = $this->_operators[rand(0, count($this->_operators) - 1)];

        return $this->_solveSimpleEquation($one, $two, $operator);
    }

    /**
     * Solves a simple equation with two given numbers
     * and one operator as defined in $this->_operators.
     *
     * Also converts the numbers to words if required.
     *
     * @access protected
     * @return array    Array with equation and solution
     */
    function _solveSimpleEquation($one, $two, $operator)
    {
        $equation = sprintf($operator, $one, $two);
        $code = '$solution=' . $equation . ';';
        eval($code);

        if ($this->_numbersToText) {
            $equation = sprintf($operator, Numbers_Words::toWords($one), Numbers_Words::toWords($two));
        }

        return array($equation, $solution);
    }

    /**
     * Return the solution to the equation.
     *
     * This method returns the CAPTCHA phrase, which is
     *  the solution to the equation.
     *
     * @access  public
     * @return  string   secret phrase
     */
    function getPhrase()
    {
        return $this->_phrase;
    }

    /**
     * Creates the captcha. This method is a placeholder,
     *  since the equation is created in _createPhrase()
     *
     * @access protected
     * @return PEAR_Error
     */
    function _createCAPTCHA() {
        //is already done in _createPhrase();
    }

    /**
     * Returns the CAPTCHA (as a string)
     *
     * @access public
     * @return string
     */
    function getCAPTCHA() {
        return $this->_equation;
    }

}//class Text_CAPTCHA_Driver_TextEquation extends Text_CAPTCHA
?>