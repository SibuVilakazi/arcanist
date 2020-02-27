<?php

/**
 * Execute a command which takes over stdin, stdout and stderr, similar to
 * `passthru()`, but which preserves TTY semantics, escapes arguments, and is
 * traceable.
 *
 * Passthru commands use the `STDIN`, `STDOUT` and `STDERR` of the parent
 * process, so input can be read from the console and output is printed to it.
 * This is primarily useful for executing things like `$EDITOR` from command
 * line scripts.
 *
 *   $exec = new PhutilExecPassthru('ls %s', $dir);
 *   $err = $exec->execute();
 *
 * You can set the current working directory for the command with
 * @{method:setCWD}, and set the environment with @{method:setEnv}.
 *
 * @task command  Executing Passthru Commands
 */
final class PhutilExecPassthru extends PhutilExecutableFuture {


/* -(  Executing Passthru Commands  )---------------------------------------- */


  /**
   * Execute this command.
   *
   * @return int  Error code returned by the subprocess.
   *
   * @task command
   */
  public function execute() {
    $command = $this->getCommand();

    $spec  = array(STDIN, STDOUT, STDERR);
    $pipes = array();

    $unmasked_command = $command->getUnmaskedString();

    if ($this->hasEnv()) {
      $env = $this->getEnv();
    } else {
      $env = null;
    }

    $cwd = $this->getCWD();

    $options = array();
    if (phutil_is_windows()) {
      // Without 'bypass_shell', things like launching vim don't work properly,
      // and we can't execute commands with spaces in them, and all commands
      // invoked from git bash fail horridly, and everything is a mess in
      // general.
      $options['bypass_shell'] = true;
    }

    $trap = new PhutilErrorTrap();
      $proc = @proc_open(
        $unmasked_command,
        $spec,
        $pipes,
        $cwd,
        $env,
        $options);
      $errors = $trap->getErrorsAsString();
    $trap->destroy();

    if (!is_resource($proc)) {
      throw new Exception(
        pht(
          'Failed to passthru %s: %s',
          'proc_open()',
          $errors));
    }

    $err = proc_close($proc);

    return $err;
  }


/* -(  Future  )------------------------------------------------------------- */


  public function isReady() {
    // This isn't really a future because it executes synchronously and has
    // full control of the console. We're just implementing the interfaces to
    // make it easier to share code with ExecFuture.

    if (!$this->hasResult()) {
      $result = $this->execute();
      $this->setResult($result);
    }

    return true;
  }



  protected function getServiceProfilerStartParameters() {
    return array(
      'type' => 'exec',
      'subtype' => 'passthru',
      'command' => phutil_string_cast($this->getCommand()),
    );
  }

  protected function getServiceProfilerResultParameters() {
    if ($this->hasResult()) {
      $err = $this->getResult();
    } else {
      $err = null;
    }

    return array(
      'err' => $err,
    );
  }

}
