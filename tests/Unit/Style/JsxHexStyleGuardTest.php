<?php

namespace Tests\Unit\Style;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class JsxHexStyleGuardTest extends TestCase
{
    private function projectRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    private function runGuardScript(string $script, array $args = []): Process
    {
        $root = $this->projectRoot();
        $command = array_merge(['bash', $root.'/scripts/style-guard/'.$script], $args);

        return new Process($command, $root, null, null, 120);
    }

    public function test_no_nuevos_hex_en_jsx_fuera_de_exenciones(): void
    {
        $process = $this->runGuardScript('check-jsx-no-hex.sh', ['--check']);
        $process->run();

        $this->assertTrue(
            $process->isSuccessful(),
            "Guard JSX hex falló:\n".$process->getErrorOutput().$process->getOutput()
            ."\nUsar tokens/var(--*) o // ponytail: style-exception en la línea anterior."
        );
    }

    public function test_no_nuevos_hex_en_css_features(): void
    {
        $process = $this->runGuardScript('check-css-features-no-hex.sh', ['--check']);
        $process->run();

        $this->assertTrue(
            $process->isSuccessful(),
            "Guard CSS features hex falló:\n".$process->getErrorOutput().$process->getOutput()
        );
    }
}
