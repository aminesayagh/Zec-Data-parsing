<?php
declare(strict_types=1);

namespace Zod;

use BadMethodCallException;
use Zod\PARSERS_KEY as PK; // PK: Parser Key

require_once ZOD_PATH . '/src/parsers/Parser.php';
require_once ZOD_PATH . '/src/parsers/Parsers.php';
require_once ZOD_PATH . '/src/Exception.php';


if(!class_exists('Zod')) {
    class Zod extends Parsers {
        use ZodErrors, ZodPath, ZodConfigs, ZodUtils, ZodDefault, ZodValue, ZodParent;        
        public function __construct(array $parsers = [], Zod $parent = null) {
            parent::__construct($parsers);
            if(!is_null($parent)) {
                $this->_clone_parent($parent);
            }
        }
        public function __clone(): void {
            $this->parsers = array_filter($this->parsers, function($parser) {
                if (is_null($parser)) {
                    return false;
                }
                if ($parser->name == 'required') {
                    return false;
                }
                return $parser->clone();
            });
        }
        /**
         * Dynamically handles method calls for the Zod class.
         *
         * @param string $name The name of the method being called.
         * @param mixed $arguments The arguments passed to the method.
         * @return mixed The result of the method call.
         * @throws ZodError If the method or parser is not found.
         */
        public function __call(string $name, ?array $arguments): mixed{

            if (method_exists($this, $name)) {
                return call_user_func_array([$this, $name], $arguments);
            }
            if (bundler()->has_parser_key($name)) {
                // echo "Parser $name found" . PHP_EOL;
                $parser = bundler()->get_parser($name);
                if(is_array($arguments) && count($arguments) > 0) {
                    $arguments = $arguments[0];
                    $parser->set_argument($arguments);
                }
                
                $this->add_parser($parser);
                return $this;
            }

            throw new BadMethodCallException("Method $name not found");
        }
        /**
         * Parses the given value using the specified parsers.
         *
         * @param mixed $value The value to be parsed.
         * @param mixed $default The default value to be used if the parsed value is null.
         * @param Zod|null $parent The parent Zod instance, if any.
         * @return Zod The current Zod instance.
         */
        public function parse(mixed $value, mixed $default = null, Zod|null $parent = null): Zod {
            $this->_errors->reset();

            $this->_value = $value;
            $this->set_default($default);

            if (!is_null($parent)) {
            }

            
            $has_required = $this->has_parser_key(PK::REQUIRED);
            if (!$has_required && is_null($value)) {
                return $this;
            }
            
            if (!is_null($parent)) {
                $this->_parent = $parent;
                $this->get_conf_from_parent($parent);
                $this->_path->extend($parent->_path);
            }

            foreach ($this->list_parsers() as $index => $parser) {
                // echo $this->_path->get_path_string() . ' : ' . $parser->name . PHP_EOL;
                if ($index > 0) {
                    $this->_path->pop();
                }
                $this->_path->push($parser->name);
                // echo "Parsing $parser->name : " . json_encode($this->_value) . " " . PHP_EOL;
                $parser->parse($this->_value, [
                    'default' => $this->_default,
                ], $this);
            }

            // push the error to the parent
            if (!is_null($parent)) {
                $parent->set_errors($this->_errors);
            }

            return $this;
        }
        /**
         * Sets the default value for the Zod instance.
         *
         * @param mixed $default The default value to be set.
         * @return Zod The updated Zod instance.
         */
        public function set_default(mixed $default): Zod {
            if(is_null($default)) {
                return $this;
            }
            $parser_of_default = clone $this;
            $parser_of_default->parse_or_throw($default);

            $this->_default = $default;
            return $this;
        }
        /**
         * Parses the given value and throws an error if it is not valid.
         *
         * @param mixed $value The value to parse.
         * @param mixed $default The default value to use if the parsed value is invalid.
         * @return Zod The parsed value.
         * @throws mixed The error(s) if the parsed value is invalid.
         */
        public function parse_or_throw(mixed $value, mixed $default = null, Zod $parent = null): mixed {
            $this->parse($value, $default);
            if (!$this->is_valid()) {
                throw $this->_errors;
            }
            return $this->get_value();
        }
    }
}

if (!function_exists('zod')) {
    /**
     * Returns an instance of the Zod class.
     *
     * @param array $parsers The array of parsers to assign to the Zod instance.
     * @param ZodErrors $errors The array of errors to assign to the Zod instance.
     * @return Zod The instance of the Zod class.
     */
    function zod(array $parsers = [], ZodErrors $errors = new ZodErrors()): Zod {
        return new Zod($parsers, $errors);
    }
}

if (!function_exists('is_zod')) {
    /**
     * Checks if the given value is an instance of the Zod class.
     *
     * @param mixed $value The value to check.
     * @return bool Returns true if the value is an instance of Zod, false otherwise.
     */
    function is_zod($value): bool {
        return is_a($value, 'Zod');
    }
}


if (!function_exists('z')) {
    /**
     * Returns an instance of the Zod class.
     *
     * @param array $parsers The array of parsers to assign to the Zod instance.
     * @param ZodErrors $errors The array of errors to assign to the Zod instance.
     * @return Zod The instance of the Zod class.
     */
    function z(array $parsers = [], ZodErrors $errors = new ZodErrors()): Zod {
        return zod($parsers, $errors);
    }
}