<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Uses XHPAST to apply lint rules to PHP or PHP+XHP.
 *
 * @group linter
 */
class ArcanistXHPASTLinter extends ArcanistLinter {

  protected $trees = array();

  const LINT_PHP_SYNTAX_ERROR         = 1;
  const LINT_UNABLE_TO_PARSE          = 2;
  const LINT_VARIABLE_VARIABLE        = 3;
  const LINT_EXTRACT_USE              = 4;
  const LINT_UNDECLARED_VARIABLE      = 5;
  const LINT_PHP_SHORT_TAG            = 6;
  const LINT_PHP_ECHO_TAG             = 7;
  const LINT_PHP_CLOSE_TAG            = 8;
  const LINT_NAMING_CONVENTIONS       = 9;
  const LINT_IMPLICIT_CONSTRUCTOR     = 10;
  const LINT_FORMATTING_CONVENTIONS   = 11;
  const LINT_DYNAMIC_DEFINE           = 12;
  const LINT_STATIC_THIS              = 13;
  const LINT_PREG_QUOTE_MISUSE        = 14;
  const LINT_PHP_OPEN_TAG             = 15;
  const LINT_TODO_COMMENT             = 16;
  const LINT_EXIT_EXPRESSION          = 17;
  const LINT_COMMENT_STYLE            = 18;
  const LINT_CLASS_FILENAME_MISMATCH  = 19;
  const LINT_TAUTOLOGICAL_EXPRESSION  = 20;
  const LINT_PLUS_OPERATOR_ON_STRINGS = 21;
  const LINT_DUPLICATE_KEYS_IN_ARRAY  = 22;
  const LINT_REUSED_ITERATORS         = 23;


  public function getLintNameMap() {
    return array(
      self::LINT_PHP_SYNTAX_ERROR         => 'PHP Syntax Error!',
      self::LINT_UNABLE_TO_PARSE          => 'Unable to Parse',
      self::LINT_VARIABLE_VARIABLE        => 'Use of Variable Variable',
      self::LINT_EXTRACT_USE              => 'Use of extract()',
      self::LINT_UNDECLARED_VARIABLE      => 'Use of Undeclared Variable',
      self::LINT_PHP_SHORT_TAG            => 'Use of Short Tag "<?"',
      self::LINT_PHP_ECHO_TAG             => 'Use of Echo Tag "<?="',
      self::LINT_PHP_CLOSE_TAG            => 'Use of Close Tag "?>"',
      self::LINT_NAMING_CONVENTIONS       => 'Naming Conventions',
      self::LINT_IMPLICIT_CONSTRUCTOR     => 'Implicit Constructor',
      self::LINT_FORMATTING_CONVENTIONS   => 'Formatting Conventions',
      self::LINT_DYNAMIC_DEFINE           => 'Dynamic define()',
      self::LINT_STATIC_THIS              => 'Use of $this in Static Context',
      self::LINT_PREG_QUOTE_MISUSE        => 'Misuse of preg_quote()',
      self::LINT_PHP_OPEN_TAG             => 'Expected Open Tag',
      self::LINT_TODO_COMMENT             => 'TODO Comment',
      self::LINT_EXIT_EXPRESSION          => 'Exit Used as Expression',
      self::LINT_COMMENT_STYLE            => 'Comment Style',
      self::LINT_CLASS_FILENAME_MISMATCH  => 'Class-Filename Mismatch',
      self::LINT_TAUTOLOGICAL_EXPRESSION  => 'Tautological Expression',
      self::LINT_PLUS_OPERATOR_ON_STRINGS => 'Not String Concatenation',
      self::LINT_DUPLICATE_KEYS_IN_ARRAY  => 'Duplicate Keys in Array',
      self::LINT_REUSED_ITERATORS         => 'Reuse of Iterator Variable',
    );
  }

  public function getLinterName() {
    return 'XHP';
  }

  public function getLintSeverityMap() {
    return array(
      self::LINT_TODO_COMMENT => ArcanistLintSeverity::SEVERITY_ADVICE,
      self::LINT_UNABLE_TO_PARSE
        => ArcanistLintSeverity::SEVERITY_WARNING,
      self::LINT_FORMATTING_CONVENTIONS
        => ArcanistLintSeverity::SEVERITY_WARNING,
      self::LINT_NAMING_CONVENTIONS
        => ArcanistLintSeverity::SEVERITY_WARNING,
    );
  }

  public function willLintPaths(array $paths) {
    $futures = array();
    foreach ($paths as $path) {
      $futures[$path] = xhpast_get_parser_future($this->getData($path));
    }
    foreach ($futures as $path => $future) {
      $this->willLintPath($path);
      try {
        $this->trees[$path] = XHPASTTree::newFromDataAndResolvedExecFuture(
          $this->getData($path),
          $future->resolve());
      } catch (XHPASTSyntaxErrorException $ex) {
        $this->raiseLintAtLine(
          $ex->getErrorLine(),
          1,
          self::LINT_PHP_SYNTAX_ERROR,
          'This file contains a syntax error: '.$ex->getMessage());
        $this->stopAllLinters();
        return;
      } catch (Exception $ex) {
        $this->raiseLintAtPath(
          self::LINT_UNABLE_TO_PARSE,
          'XHPAST could not parse this file, probably because the AST is too '.
          'deep. Some lint issues may not have been detected. You may safely '.
          'ignore this warning.');
        return;
      }
    }
  }

  public function getXHPASTTreeForPath($path) {
    return idx($this->trees, $path);
  }

  public function lintPath($path) {
    if (empty($this->trees[$path])) {
      return;
    }

    $root = $this->trees[$path]->getRootNode();

    $this->lintUseOfThisInStaticMethods($root);
    $this->lintDynamicDefines($root);
    $this->lintSurpriseConstructors($root);
    $this->lintPHPTagUse($root);
    $this->lintVariableVariables($root);
    $this->lintTODOComments($root);
    $this->lintExitExpressions($root);
    $this->lintSpaceAroundBinaryOperators($root);
    $this->lintSpaceAfterControlStatementKeywords($root);
    $this->lintParenthesesShouldHugExpressions($root);
    $this->lintNamingConventions($root);
    $this->lintPregQuote($root);
    $this->lintUndeclaredVariables($root);
    $this->lintArrayIndexWhitespace($root);
    $this->lintHashComments($root);
    $this->lintPrimaryDeclarationFilenameMatch($root);
    $this->lintTautologicalExpressions($root);
    $this->lintPlusOperatorOnStrings($root);
    $this->lintDuplicateKeysInArray($root);
    $this->lintReusedIterators($root);
    $this->lintBraceFormatting($root);
  }

  private function lintBraceFormatting($root) {

    foreach ($root->selectDescendantsOfType('n_STATEMENT_LIST') as $list) {
      $tokens = $list->getTokens();
      if (!$tokens || head($tokens)->getValue() != '{') {
        continue;
      }
      list($before, $after) = $list->getSurroundingNonsemanticTokens();
      if (count($before) == 1) {
        $before = reset($before);
        if ($before->getValue() != ' ') {
          $this->raiseLintAtToken(
            $before,
            self::LINT_FORMATTING_CONVENTIONS,
            'Put opening braces on the same line as control statements and '.
            'declarations, with a single space before them.',
            ' ');
        }
      }
    }

  }

  private function lintTautologicalExpressions($root) {
    $expressions = $root->selectDescendantsOfType('n_BINARY_EXPRESSION');

    static $operators = array(
      '-'   => true,
      '/'   => true,
      '-='  => true,
      '/='  => true,
      '<='  => true,
      '<'   => true,
      '=='  => true,
      '===' => true,
      '!='  => true,
      '!==' => true,
      '>='  => true,
      '>'   => true,
    );

    static $logical = array(
      '||'  => true,
      '&&'  => true,
    );

    foreach ($expressions as $expr) {
      $operator = $expr->getChildByIndex(1)->getConcreteString();
      if (!empty($operators[$operator])) {
        $left = $expr->getChildByIndex(0)->getSemanticString();
        $right = $expr->getChildByIndex(2)->getSemanticString();

        if ($left == $right) {
          $this->raiseLintAtNode(
            $expr,
            self::LINT_TAUTOLOGICAL_EXPRESSION,
            'Both sides of this expression are identical, so it always '.
            'evaluates to a constant.');
        }
      }

      if (!empty($logical[$operator])) {
        $left = $expr->getChildByIndex(0)->getSemanticString();
        $right = $expr->getChildByIndex(2)->getSemanticString();

        // NOTE: These will be null to indicate "could not evaluate".
        $left = $this->evaluateStaticBoolean($left);
        $right = $this->evaluateStaticBoolean($right);

        if (($operator == '||' && ($left === true || $right === true)) ||
            ($operator == '&&' && ($left === false || $right === false))) {
          $this->raiseLintAtNode(
            $expr,
            self::LINT_TAUTOLOGICAL_EXPRESSION,
            'The logical value of this expression is static. Did you forget '.
            'to remove some debugging code?');
        }
      }
    }
  }


  /**
   * Statically evaluate a boolean value from an XHP tree.
   *
   * TODO: Improve this and move it to XHPAST proper?
   *
   * @param  string The "semantic string" of a single value.
   * @return mixed  ##true## or ##false## if the value could be evaluated
   *                statically; ##null## if static evaluation was not possible.
   */
  private function evaluateStaticBoolean($string) {
    switch (strtolower($string)) {
      case '0':
      case 'null':
      case 'false':
        return false;
      case '1':
      case 'true':
        return true;
    }
    return null;
  }


  protected function lintHashComments($root) {
    $tokens = $root->getTokens();
    foreach ($tokens as $token) {
      if ($token->getTypeName() == 'T_COMMENT') {
        $value = $token->getValue();
        if ($value[0] == '#') {
          $this->raiseLintAtOffset(
            $token->getOffset(),
            self::LINT_COMMENT_STYLE,
            'Use "//" single-line comments, not "#".',
            '#',
            '//');
        }
      }
    }
  }

  /**
   * Find cases where loops get nested inside each other but use the same
   * iterator variable. For example:
   *
   *  COUNTEREXAMPLE
   *  foreach ($list as $thing) {
   *    foreach ($stuff as $thing) { // <-- Raises an error for reuse of $thing
   *      // ...
   *    }
   *  }
   *
   */
  private function lintReusedIterators($root) {
    $used_vars = array();

    $for_loops = $root->selectDescendantsOfType('n_FOR');
    foreach ($for_loops as $for_loop) {
      $var_map = array();

      // Find all the variables that are assigned to in the for() expression.
      $for_expr = $for_loop->getChildOfType(0, 'n_FOR_EXPRESSION');
      $bin_exprs = $for_expr->selectDescendantsOfType('n_BINARY_EXPRESSION');
      foreach ($bin_exprs as $bin_expr) {
        if ($bin_expr->getChildByIndex(1)->getConcreteString() == '=') {
          $var_map[$bin_expr->getChildByIndex(0)->getConcreteString()] = true;
        }
      }

      $used_vars[$for_loop->getID()] = $var_map;
    }

    $foreach_loops = $root->selectDescendantsOfType('n_FOREACH');
    foreach ($foreach_loops as $foreach_loop) {
      $var_map = array();

      $foreach_expr = $foreach_loop->getChildOftype(0, 'n_FOREACH_EXPRESSION');

      // We might use one or two vars, i.e. "foreach ($x as $y => $z)" or
      // "foreach ($x as $y)".
      $possible_used_vars = array(
        $foreach_expr->getChildByIndex(1),
        $foreach_expr->getChildByIndex(2),
      );
      foreach ($possible_used_vars as $var) {
        if ($var->getTypeName() == 'n_EMPTY') {
          continue;
        }
        $name = $var->getConcreteString();
        $name = trim($name, '&'); // Get rid of ref silliness.
        $var_map[$name] = true;
      }

      $used_vars[$foreach_loop->getID()] = $var_map;
    }

    $all_loops = $for_loops->add($foreach_loops);
    foreach ($all_loops as $loop) {
      $child_for_loops = $loop->selectDescendantsOfType('n_FOR');
      $child_foreach_loops = $loop->selectDescendantsOfType('n_FOREACH');
      $child_loops = $child_for_loops->add($child_foreach_loops);

      $outer_vars = $used_vars[$loop->getID()];
      foreach ($child_loops as $inner_loop) {
        $inner_vars = $used_vars[$inner_loop->getID()];
        $shared = array_intersect_key($outer_vars, $inner_vars);
        if ($shared) {
          $shared_desc = implode(', ', array_keys($shared));
          $this->raiseLintAtNode(
            $inner_loop->getChildByIndex(0),
            self::LINT_REUSED_ITERATORS,
            "This loop reuses iterator variables ({$shared_desc}) from an ".
            "outer loop. You might be clobbering the outer iterator. Change ".
            "the inner loop to use a different iterator name.");
        }
      }
    }
  }

  protected function lintVariableVariables($root) {
    $vvars = $root->selectDescendantsOfType('n_VARIABLE_VARIABLE');
    foreach ($vvars as $vvar) {
      $this->raiseLintAtNode(
        $vvar,
        self::LINT_VARIABLE_VARIABLE,
        'Rewrite this code to use an array. Variable variables are unclear '.
        'and hinder static analysis.');
    }
  }

  protected function lintUndeclaredVariables($root) {
    // These things declare variables in a function:
    //    Explicit parameters
    //    Assignment
    //    Assignment via list()
    //    Static
    //    Global
    //    Lexical vars
    //    Builtins ($this)
    //    foreach()
    //    catch
    //
    // These things make lexical scope unknowable:
    //    Use of extract()
    //    Assignment to variable variables ($$x)
    //    Global with variable variables
    //
    // These things don't count as "using" a variable:
    //    isset()
    //    empty()
    //    Static class variables
    //
    // The general approach here is to find each function/method declaration,
    // then:
    //
    //  1. Identify all the variable declarations, and where they first occur
    //     in the function/method declaration.
    //  2. Identify all the uses that don't really count (as above).
    //  3. Everything else must be a use of a variable.
    //  4. For each variable, check if any uses occur before the declaration
    //     and warn about them.
    //
    // We also keep track of where lexical scope becomes unknowable (e.g.,
    // because the function calls extract() or uses dynamic variables,
    // preventing us from keeping track of which variables are defined) so we
    // can stop issuing warnings after that.

    $fdefs = $root->selectDescendantsOfType('n_FUNCTION_DECLARATION');
    $mdefs = $root->selectDescendantsOfType('n_METHOD_DECLARATION');
    $defs = $fdefs->add($mdefs);

    foreach ($defs as $def) {

      // We keep track of the first offset where scope becomes unknowable, and
      // silence any warnings after that. Default it to INT_MAX so we can min()
      // it later to keep track of the first problem we encounter.
      $scope_destroyed_at = PHP_INT_MAX;

      $declarations = array(
        '$this'     => 0,
        '$GLOBALS'  => 0,
        '$_SERVER'  => 0,
        '$_GET'     => 0,
        '$_POST'    => 0,
        '$_FILES'   => 0,
        '$_COOKIE'  => 0,
        '$_SESSION' => 0,
        '$_REQUEST' => 0,
        '$_ENV'     => 0,
      );
      $declaration_tokens = array();
      $exclude_tokens = array();
      $vars = array();

      // First up, find all the different kinds of declarations, as explained
      // above. Put the tokens into the $vars array.

      $param_list = $def->getChildOfType(3, 'n_DECLARATION_PARAMETER_LIST');
      $param_vars = $param_list->selectDescendantsOfType('n_VARIABLE');
      foreach ($param_vars as $var) {
        $vars[] = $var;
      }

      // This is PHP5.3 closure syntax: function () use ($x) {};
      $lexical_vars = $def
        ->getChildByIndex(4)
        ->selectDescendantsOfType('n_VARIABLE');
      foreach ($lexical_vars as $var) {
        $vars[] = $var;
      }

      $body = $def->getChildByIndex(5);
      if ($body->getTypeName() == 'n_EMPTY') {
        // Abstract method declaration.
        continue;
      }

      $static_vars = $body
        ->selectDescendantsOfType('n_STATIC_DECLARATION')
        ->selectDescendantsOfType('n_VARIABLE');
      foreach ($static_vars as $var) {
        $vars[] = $var;
      }


      $global_vars = $body
        ->selectDescendantsOfType('n_GLOBAL_DECLARATION_LIST');
      foreach ($global_vars as $var_list) {
        foreach ($var_list->getChildren() as $var) {
          if ($var->getTypeName() == 'n_VARIABLE') {
            $vars[] = $var;
          } else {
            // Dynamic global variable, i.e. "global $$x;".
            $scope_destroyed_at = min($scope_destroyed_at, $var->getOffset());
            // An error is raised elsewhere, no need to raise here.
          }
        }
      }

      $catches = $body
        ->selectDescendantsOfType('n_CATCH')
        ->selectDescendantsOfType('n_VARIABLE');
      foreach ($catches as $var) {
        $vars[] = $var;
      }

      $foreaches = $body->selectDescendantsOfType('n_FOREACH_EXPRESSION');
      foreach ($foreaches as $foreach_expr) {
        $key_var = $foreach_expr->getChildByIndex(1);
        if ($key_var->getTypeName() == 'n_VARIABLE') {
          $vars[] = $key_var;
        }

        $value_var = $foreach_expr->getChildByIndex(2);
        if ($value_var->getTypeName() == 'n_VARIABLE') {
          $vars[] = $value_var;
        } else {
          // The root-level token may be a reference, as in:
          //    foreach ($a as $b => &$c) { ... }
          // Reach into the n_VARIABLE_REFERENCE node to grab the n_VARIABLE
          // node.
          $vars[] = $value_var->getChildOfType(0, 'n_VARIABLE');
        }
      }

      $binary = $body->selectDescendantsOfType('n_BINARY_EXPRESSION');
      foreach ($binary as $expr) {
        if ($expr->getChildByIndex(1)->getConcreteString() != '=') {
          continue;
        }
        $lval = $expr->getChildByIndex(0);
        if ($lval->getTypeName() == 'n_VARIABLE') {
          $vars[] = $lval;
        } else if ($lval->getTypeName() == 'n_LIST') {
          // Recursivey grab everything out of list(), since the grammar
          // permits list() to be nested. Also note that list() is ONLY valid
          // as an lval assignments, so we could safely lift this out of the
          // n_BINARY_EXPRESSION branch.
          $assign_vars = $lval->selectDescendantsOfType('n_VARIABLE');
          foreach ($assign_vars as $var) {
            $vars[] = $var;
          }
        }

        if ($lval->getTypeName() == 'n_VARIABLE_VARIABLE') {
          $scope_destroyed_at = min($scope_destroyed_at, $lval->getOffset());
          // No need to raise here since we raise an error elsewhere.
        }
      }

      $calls = $body->selectDescendantsOfType('n_FUNCTION_CALL');
      foreach ($calls as $call) {
        $name = strtolower($call->getChildByIndex(0)->getConcreteString());

        if ($name == 'empty' || $name == 'isset') {
          $params = $call
            ->getChildOfType(1, 'n_CALL_PARAMETER_LIST')
            ->selectDescendantsOfType('n_VARIABLE');
          foreach ($params as $var) {
            $exclude_tokens[$var->getID()] = true;
          }
          continue;
        }
        if ($name != 'extract') {
          continue;
        }
        $scope_destroyed_at = min($scope_destroyed_at, $call->getOffset());
        $this->raiseLintAtNode(
          $call,
          self::LINT_EXTRACT_USE,
          'Avoid extract(). It is confusing and hinders static analysis.');
      }

      // Now we have every declaration. Build two maps, one which just keeps
      // track of which tokens are part of declarations ($declaration_tokens)
      // and one which has the first offset where a variable is declared
      // ($declarations).

      foreach ($vars as $var) {
        $concrete = $this->getConcreteVariableString($var);
        $declarations[$concrete] = min(
          idx($declarations, $concrete, PHP_INT_MAX),
          $var->getOffset());
        $declaration_tokens[$var->getID()] = true;
      }

      // Excluded tokens are ones we don't "count" as being uses, described
      // above. Put them into $exclude_tokens.

      $class_statics = $body
        ->selectDescendantsOfType('n_CLASS_STATIC_ACCESS');
      $class_static_vars = $class_statics
        ->selectDescendantsOfType('n_VARIABLE');
      foreach ($class_static_vars as $var) {
        $exclude_tokens[$var->getID()] = true;
      }

      // Issue a warning for every variable token, unless it appears in a
      // declaration, we know about a prior declaration, we have explicitly
      // exlcuded it, or scope has been made unknowable before it appears.

      $all_vars = $body->selectDescendantsOfType('n_VARIABLE');
      $issued_warnings = array();
      foreach ($all_vars as $var) {
        if (isset($declaration_tokens[$var->getID()])) {
          // We know this is part of a declaration, so it's fine.
          continue;
        }
        if (isset($exclude_tokens[$var->getID()])) {
          // We know this is part of isset() or similar, so it's fine.
          continue;
        }
        if ($var->getOffset() >= $scope_destroyed_at) {
          // This appears after an extract() or $$var so we have no idea
          // whether it's legitimate or not. We raised a harshly-worded warning
          // when scope was made unknowable, so just ignore anything we can't
          // figure out.
          continue;
        }
        $concrete = $this->getConcreteVariableString($var);
        if ($var->getOffset() >= idx($declarations, $concrete, PHP_INT_MAX)) {
          // The use appears after the variable is declared, so it's fine.
          continue;
        }
        if (!empty($issued_warnings[$concrete])) {
          // We've already issued a warning for this variable so we don't need
          // to issue another one.
          continue;
        }
        $this->raiseLintAtNode(
          $var,
          self::LINT_UNDECLARED_VARIABLE,
          'Declare variables prior to use (even if you are passing them '.
          'as reference parameters). You may have misspelled this '.
          'variable name.');
        $issued_warnings[$concrete] = true;
      }
    }
  }

  private function getConcreteVariableString($var) {
    $concrete = $var->getConcreteString();
    // Strip off curly braces as in $obj->{$property}.
    $concrete = trim($concrete, '{}');
    return $concrete;
  }

  protected function lintPHPTagUse($root) {
    $tokens = $root->getTokens();
    foreach ($tokens as $token) {
      if ($token->getTypeName() == 'T_OPEN_TAG') {
        if (trim($token->getValue()) == '<?') {
          $this->raiseLintAtToken(
            $token,
            self::LINT_PHP_SHORT_TAG,
            'Use the full form of the PHP open tag, "<?php".',
            "<?php\n");
        }
        break;
      } else if ($token->getTypeName() == 'T_OPEN_TAG_WITH_ECHO') {
        $this->raiseLintAtToken(
          $token,
          self::LINT_PHP_ECHO_TAG,
          'Avoid the PHP echo short form, "<?=".');
        break;
      } else {
        if (!preg_match('/^#!/', $token->getValue())) {
          $this->raiseLintAtToken(
            $token,
            self::LINT_PHP_OPEN_TAG,
            'PHP files should start with "<?php", which may be preceded by '.
            'a "#!" line for scripts.');
        }
      }
    }
    foreach ($tokens as $token) {
      if ($token->getTypeName() == 'T_CLOSE_TAG') {
        $this->raiseLintAtToken(
          $token,
          self::LINT_PHP_CLOSE_TAG,
          'Do not use the PHP closing tag, "?>".');
      }
    }
  }

  protected function lintNamingConventions($root) {

    // We're going to build up a list of <type, name, token, error> tuples
    // and then try to instantiate a hook class which has the opportunity to
    // override us.
    $names = array();

    $classes = $root->selectDescendantsOfType('n_CLASS_DECLARATION');
    foreach ($classes as $class) {
      $name_token = $class->getChildByIndex(1);
      $name_string = $name_token->getConcreteString();
      $is_xhp = ($name_string[0] == ':');
      if ($is_xhp) {
        $names[] = array(
          'xhp-class',
          $name_string,
          $name_token,
          $this->isLowerCaseWithXHP($name_string)
            ? null
            : 'Follow naming conventions: XHP elements should be named using '.
              'lower case.',
        );
      } else {
        $names[] = array(
          'class',
          $name_string,
          $name_token,
          $this->isUpperCamelCase($name_string)
            ? null
            : 'Follow naming conventions: classes should be named using '.
              'UpperCamelCase.',
        );
      }
    }

    $ifaces = $root->selectDescendantsOfType('n_INTERFACE_DECLARATION');
    foreach ($ifaces as $iface) {
      $name_token = $iface->getChildByIndex(1);
      $name_string = $name_token->getConcreteString();
      $names[] = array(
        'interface',
        $name_string,
        $name_token,
        $this->isUpperCamelCase($name_string)
          ? null
          : 'Follow naming conventions: interfaces should be named using '.
            'UpperCamelCase.',
      );
    }


    $functions = $root->selectDescendantsOfType('n_FUNCTION_DECLARATION');
    foreach ($functions as $function) {
      $name_token = $function->getChildByIndex(2);
      if ($name_token->getTypeName() == 'n_EMPTY') {
        // Unnamed closure.
        continue;
      }
      $name_string = $name_token->getConcreteString();
      $names[] = array(
        'function',
        $name_string,
        $name_token,
        $this->isLowercaseWithUnderscores($name_string)
          ? null
          : 'Follow naming conventions: functions should be named using '.
            'lowercase_with_underscores.',
      );
    }


    $methods = $root->selectDescendantsOfType('n_METHOD_DECLARATION');
    foreach ($methods as $method) {
      $name_token = $method->getChildByIndex(2);
      $name_string = $name_token->getConcreteString();
      $names[] = array(
        'method',
        $name_string,
        $name_token,
        $this->isLowerCamelCase($name_string)
          ? null
          : 'Follow naming conventions: methods should be named using '.
            'lowerCamelCase.',
      );
    }


    $params = $root->selectDescendantsOfType('n_DECLARATION_PARAMETER_LIST');
    foreach ($params as $param_list) {
      foreach ($param_list->getChildren() as $param) {
        $name_token = $param->getChildByIndex(1);
        $name_string = $name_token->getConcreteString();
        $names[] = array(
          'parameter',
          $name_string,
          $name_token,
          $this->isLowercaseWithUnderscores($name_string)
            ? null
            : 'Follow naming conventions: parameters should be named using '.
              'lowercase_with_underscores.',
        );
      }
    }


    $constants = $root->selectDescendantsOfType(
      'n_CLASS_CONSTANT_DECLARATION_LIST');
    foreach ($constants as $constant_list) {
      foreach ($constant_list->getChildren() as $constant) {
        $name_token = $constant->getChildByIndex(0);
        $name_string = $name_token->getConcreteString();
        $names[] = array(
          'constant',
          $name_string,
          $name_token,
          $this->isUppercaseWithUnderscores($name_string)
            ? null
            : 'Follow naming conventions: class constants should be named '.
              'using UPPERCASE_WITH_UNDERSCORES.',
        );
      }
    }

    $props = $root->selectDescendantsOfType('n_CLASS_MEMBER_DECLARATION_LIST');
    foreach ($props as $prop_list) {
      foreach ($prop_list->getChildren() as $prop) {
        if ($prop->getTypeName() == 'n_CLASS_MEMBER_MODIFIER_LIST') {
          continue;
        }
        $name_token = $prop->getChildByIndex(0);
        $name_string = $name_token->getConcreteString();
        $names[] = array(
          'member',
          $name_string,
          $name_token,
          $this->isLowerCamelCase($name_string)
            ? null
            : 'Follow naming conventions: class properties should be named '.
              'using lowerCamelCase.',
        );
      }
    }

    $engine = $this->getEngine();
    $working_copy = $engine->getWorkingCopy();

    if ($working_copy) {
      // If a naming hook is configured, give it a chance to override the
      // default results for all the symbol names.
      $hook_class = $working_copy->getConfig('lint.xhpast.naminghook');
      if ($hook_class) {
        $hook_obj = newv($hook_class, array());
        foreach ($names as $k => $name_attrs) {
          list($type, $name, $token, $default) = $name_attrs;
          $result = $hook_obj->lintSymbolName($type, $name, $default);
          $names[$k][3] = $result;
        }
      }
    }

    // Raise anything we're left with.
    foreach ($names as $k => $name_attrs) {
      list($type, $name, $token, $result) = $name_attrs;
      if ($result) {
        $this->raiseLintAtNode(
          $token,
          self::LINT_NAMING_CONVENTIONS,
          $result);
      }
    }
  }

  protected function isUpperCamelCase($str) {
    return preg_match('/^[A-Z][A-Za-z0-9]*$/', $str);
  }

  protected function isLowerCamelCase($str) {
    //  Allow initial "__" for magic methods like __construct; we could also
    //  enumerate these explicitly.
    return preg_match('/^\$?(?:__)?[a-z][A-Za-z0-9]*$/', $str);
  }

  protected function isUppercaseWithUnderscores($str) {
    return preg_match('/^[A-Z0-9_]+$/', $str);
  }

  protected function isLowercaseWithUnderscores($str) {
    return preg_match('/^[&]?\$?[a-z0-9_]+$/', $str);
  }

  protected function isLowercaseWithXHP($str) {
    return preg_match('/^:[a-z0-9_:-]+$/', $str);
  }

  protected function lintSurpriseConstructors($root) {
    $classes = $root->selectDescendantsOfType('n_CLASS_DECLARATION');
    foreach ($classes as $class) {
      $class_name = $class->getChildByIndex(1)->getConcreteString();
      $methods = $class->selectDescendantsOfType('n_METHOD_DECLARATION');
      foreach ($methods as $method) {
        $method_name_token = $method->getChildByIndex(2);
        $method_name = $method_name_token->getConcreteString();
        if (strtolower($class_name) == strtolower($method_name)) {
          $this->raiseLintAtNode(
            $method_name_token,
            self::LINT_IMPLICIT_CONSTRUCTOR,
            'Name constructors __construct() explicitly. This method is a '.
            'constructor because it has the same name as the class it is '.
            'defined in.');
        }
      }
    }
  }

  protected function lintParenthesesShouldHugExpressions($root) {
    $calls = $root->selectDescendantsOfType('n_CALL_PARAMETER_LIST');
    $controls = $root->selectDescendantsOfType('n_CONTROL_CONDITION');
    $fors = $root->selectDescendantsOfType('n_FOR_EXPRESSION');
    $foreach = $root->selectDescendantsOfType('n_FOREACH_EXPRESSION');
    $decl = $root->selectDescendantsOfType('n_DECLARATION_PARAMETER_LIST');

    $all_paren_groups = $calls
      ->add($controls)
      ->add($fors)
      ->add($foreach)
      ->add($decl);
    foreach ($all_paren_groups as $group) {
      $tokens = $group->getTokens();

      $token_o = array_shift($tokens);
      $token_c = array_pop($tokens);
      if ($token_o->getTypeName() != '(') {
        throw new Exception('Expected open paren!');
      }
      if ($token_c->getTypeName() != ')') {
        throw new Exception('Expected close paren!');
      }

      $nonsem_o = $token_o->getNonsemanticTokensAfter();
      $nonsem_c = $token_c->getNonsemanticTokensBefore();

      if (!$nonsem_o) {
        continue;
      }

      $raise = array();

      $string_o = implode('', mpull($nonsem_o, 'getValue'));
      if (preg_match('/^[ ]+$/', $string_o)) {
        $raise[] = array($nonsem_o, $string_o);
      }

      if ($nonsem_o !== $nonsem_c) {
        $string_c = implode('', mpull($nonsem_c, 'getValue'));
        if (preg_match('/^[ ]+$/', $string_c)) {
          $raise[] = array($nonsem_c, $string_c);
        }
      }

      foreach ($raise as $warning) {
        list($tokens, $string) = $warning;
        $this->raiseLintAtOffset(
          reset($tokens)->getOffset(),
          self::LINT_FORMATTING_CONVENTIONS,
          'Parentheses should hug their contents.',
          $string,
          '');
      }
    }
  }

  protected function lintSpaceAfterControlStatementKeywords($root) {
    foreach ($root->getTokens() as $id => $token) {
      switch ($token->getTypeName()) {
        case 'T_IF':
        case 'T_ELSE':
        case 'T_FOR':
        case 'T_FOREACH':
        case 'T_WHILE':
        case 'T_DO':
        case 'T_SWITCH':
          $after = $token->getNonsemanticTokensAfter();
          if (empty($after)) {
            $this->raiseLintAtToken(
              $token,
              self::LINT_FORMATTING_CONVENTIONS,
              'Convention: put a space after control statements.',
              $token->getValue().' ');
          }
          break;
      }
    }
  }

  protected function lintSpaceAroundBinaryOperators($root) {
    $expressions = $root->selectDescendantsOfType('n_BINARY_EXPRESSION');
    foreach ($expressions as $expression) {
      $operator = $expression->getChildByIndex(1);
      $operator_value = $operator->getConcreteString();
      if ($operator_value == '.') {
        // TODO: implement this check
        continue;
      } else {
        list($before, $after) = $operator->getSurroundingNonsemanticTokens();

        $replace = null;
        if (empty($before) && empty($after)) {
          $replace = " {$operator_value} ";
        } else if (empty($before)) {
          $replace = " {$operator_value}";
        } else if (empty($after)) {
          $replace = "{$operator_value} ";
        }

        if ($replace !== null) {
          $this->raiseLintAtNode(
            $operator,
            self::LINT_FORMATTING_CONVENTIONS,
            'Convention: logical and arithmetic operators should be '.
            'surrounded by whitespace.',
            $replace);
        }
      }
    }
  }

  protected function lintDynamicDefines($root) {
    $calls = $root->selectDescendantsOfType('n_FUNCTION_CALL');
    foreach ($calls as $call) {
      $name = $call->getChildByIndex(0)->getConcreteString();
      if (strtolower($name) == 'define') {
        $parameter_list = $call->getChildOfType(1, 'n_CALL_PARAMETER_LIST');
        $defined = $parameter_list->getChildByIndex(0);
        if (!$defined->isStaticScalar()) {
          $this->raiseLintAtNode(
            $defined,
            self::LINT_DYNAMIC_DEFINE,
            'First argument to define() must be a string literal.');
        }
      }
    }
  }

  protected function lintUseOfThisInStaticMethods($root) {
    $classes = $root->selectDescendantsOfType('n_CLASS_DECLARATION');
    foreach ($classes as $class) {
      $methods = $class->selectDescendantsOfType('n_METHOD_DECLARATION');
      foreach ($methods as $method) {

        $attributes = $method
          ->getChildByIndex(0, 'n_METHOD_MODIFIER_LIST')
          ->selectDescendantsOfType('n_STRING');

        $method_is_static = false;
        $method_is_abstract = false;
        foreach ($attributes as $attribute) {
          if (strtolower($attribute->getConcreteString()) == 'static') {
            $method_is_static = true;
          }
          if (strtolower($attribute->getConcreteString()) == 'abstract') {
            $method_is_abstract = true;
          }
        }

        if ($method_is_abstract) {
          continue;
        }

        if (!$method_is_static) {
          continue;
        }

        $body = $method->getChildOfType(5, 'n_STATEMENT_LIST');

        $variables = $body->selectDescendantsOfType('n_VARIABLE');
        foreach ($variables as $variable) {
          if ($method_is_static &&
              strtolower($variable->getConcreteString()) == '$this') {
            $this->raiseLintAtNode(
              $variable,
              self::LINT_STATIC_THIS,
              'You can not reference "$this" inside a static method.');
          }
        }
      }
    }
  }

  /**
   * preg_quote() takes two arguments, but the second one is optional because
   * PHP is awesome.  If you don't pass a second argument, you're probably
   * going to get something wrong.
   */
  protected function lintPregQuote($root) {
    $function_calls = $root->selectDescendantsOfType('n_FUNCTION_CALL');
    foreach ($function_calls as $call) {
      $name = $call->getChildByIndex(0)->getConcreteString();
      if (strtolower($name) === 'preg_quote') {
        $parameter_list = $call->getChildOfType(1, 'n_CALL_PARAMETER_LIST');
        if (count($parameter_list->getChildren()) !== 2) {
          $this->raiseLintAtNode(
            $call,
            self::LINT_PREG_QUOTE_MISUSE,
            'You should always pass two arguments to preg_quote(), so that ' .
            'preg_quote() knows which delimiter to escape.');
        }
      }
    }
  }

  /**
   * Exit is parsed as an expression, but using it as such is almost always
   * wrong. That is, this is valid:
   *
   *    strtoupper(33 * exit - 6);
   *
   * When exit is used as an expression, it causes the program to terminate with
   * exit code 0. This is likely not what is intended; these statements have
   * different effects:
   *
   *    exit(-1);
   *    exit -1;
   *
   * The former exits with a failure code, the latter with a success code!
   */
  protected function lintExitExpressions($root) {
    $unaries = $root->selectDescendantsOfType('n_UNARY_PREFIX_EXPRESSION');
    foreach ($unaries as $unary) {
      $operator = $unary->getChildByIndex(0)->getConcreteString();
      if (strtolower($operator) == 'exit') {
        if ($unary->getParentNode()->getTypeName() != 'n_STATEMENT') {
          $this->raiseLintAtNode(
            $unary,
            self::LINT_EXIT_EXPRESSION,
            "Use exit as a statement, not an expression.");
        }
      }
    }
  }

  private function lintArrayIndexWhitespace($root) {
    $indexes = $root->selectDescendantsOfType('n_INDEX_ACCESS');
    foreach ($indexes as $index) {
      $tokens = $index->getChildByIndex(0)->getTokens();
      $last = array_pop($tokens);
      $trailing = $last->getNonsemanticTokensAfter();
      $trailing_text = implode('', mpull($trailing, 'getValue'));
      if (preg_match('/^ +$/', $trailing_text)) {
        $this->raiseLintAtOffset(
          $last->getOffset() + strlen($last->getValue()),
          self::LINT_FORMATTING_CONVENTIONS,
          'Convention: no spaces before index access.',
          $trailing_text,
          '');
      }
    }
  }

  protected function lintTODOComments($root) {
    $tokens = $root->getTokens();
    foreach ($tokens as $token) {
      if (!$token->isComment()) {
        continue;
      }

      $value = $token->getValue();
      $matches = null;
      $preg = preg_match_all(
        '/TODO/',
        $value,
        $matches,
        PREG_OFFSET_CAPTURE);

      foreach ($matches[0] as $match) {
        list($string, $offset) = $match;
        $this->raiseLintAtOffset(
          $token->getOffset() + $offset,
          self::LINT_TODO_COMMENT,
          'This comment has a TODO.',
          $string);
      }
    }
  }

  /**
   * Lint that if the file declares exactly one interface or class,
   * the name of the file matches the name of the class,
   * unless the classname is funky like an XHP element.
   */
  private function lintPrimaryDeclarationFilenameMatch($root) {
    $classes = $root->selectDescendantsOfType('n_CLASS_DECLARATION');
    $interfaces = $root->selectDescendantsOfType('n_INTERFACE_DECLARATION');

    if (count($classes) + count($interfaces) != 1) {
      return;
    }

    $declarations = count($classes) ? $classes : $interfaces;
    $declarations->rewind();
    $declaration = $declarations->current();

    $decl_name = $declaration->getChildByIndex(1);
    $decl_string = $decl_name->getConcreteString();

    // Exclude strangely named classes, e.g. XHP tags.
    if (!preg_match('/^\w+$/', $decl_string)) {
      return;
    }

    $rename = $decl_string.'.php';

    $path = $this->getActivePath();
    $filename = basename($path);

    if ($rename == $filename) {
      return;
    }

    $this->raiseLintAtNode(
      $decl_name,
      self::LINT_CLASS_FILENAME_MISMATCH,
      "The name of this file differs from the name of the class or interface ".
      "it declares. Rename the file to '{$rename}'."
    );
  }

  private function lintPlusOperatorOnStrings($root) {
    $binops = $root->selectDescendantsOfType('n_BINARY_EXPRESSION');
    foreach ($binops as $binop) {
      $op = $binop->getChildByIndex(1);
      if ($op->getConcreteString() != '+') {
        continue;
      }

      $left = $binop->getChildByIndex(0);
      $right = $binop->getChildByIndex(2);
      if (($left->getTypeName() == 'n_STRING_SCALAR') ||
          ($right->getTypeName() == 'n_STRING_SCALAR')) {
        $this->raiseLintAtNode(
          $binop,
          self::LINT_PLUS_OPERATOR_ON_STRINGS,
          "In PHP, '.' is the string concatenation operator, not '+'. This ".
          "expression uses '+' with a string literal as an operand.");
      }
    }
  }

  /**
   * Finds duplicate keys in array initializers, as in
   * array(1 => 'anything', 1 => 'foo').  Since the first entry is ignored,
   * this is almost certainly an error.
   */
  private function lintDuplicateKeysInArray($root) {
    $array_literals = $root->selectDescendantsOfType('n_ARRAY_LITERAL');
    foreach ($array_literals as $array_literal) {
      $nodes_by_key = array();
      $keys_warn = array();
      $list_node = $array_literal->getChildByIndex(0);
      foreach ($list_node->getChildren() as $array_entry) {
        $key_node = $array_entry->getChildByIndex(0);

        switch ($key_node->getTypeName()) {
          case 'n_STRING_SCALAR':
          case 'n_NUMERIC_SCALAR':
            // Scalars: array(1 => 'v1', '1' => 'v2');
            $key = 'scalar:'.(string)$key_node->evalStatic();
            break;

          case 'n_SYMBOL_NAME':
          case 'n_VARIABLE':
          case 'n_CLASS_STATIC_ACCESS':
            // Constants: array(CONST => 'v1', CONST => 'v2');
            // Variables: array($a => 'v1', $a => 'v2');
            // Class constants and vars: array(C::A => 'v1', C::A => 'v2');
            $key = $key_node->getTypeName().':'.$key_node->getConcreteString();
            break;

          default:
            $key = null;
        }

        if ($key !== null) {
          if (isset($nodes_by_key[$key])) {
            $keys_warn[$key] = true;
          }
          $nodes_by_key[$key][] = $key_node;
        }
      }

      foreach ($keys_warn as $key => $_) {
        foreach ($nodes_by_key[$key] as $node) {
          $this->raiseLintAtNode(
            $node,
            self::LINT_DUPLICATE_KEYS_IN_ARRAY,
            "Duplicate key in array initializer. PHP will ignore all ".
            "but the last entry.");
        }
      }
    }
  }

  protected function raiseLintAtToken(
    XHPASTToken $token,
    $code,
    $desc,
    $replace = null) {
    return $this->raiseLintAtOffset(
      $token->getOffset(),
      $code,
      $desc,
      $token->getValue(),
      $replace);
  }

  protected function raiseLintAtNode(
    XHPASTNode $node,
    $code,
    $desc,
    $replace = null) {
    return $this->raiseLintAtOffset(
      $node->getOffset(),
      $code,
      $desc,
      $node->getConcreteString(),
      $replace);
  }

}
