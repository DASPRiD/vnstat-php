<?php
namespace Vnstat;

use RuntimeException;

class Reader
{
    /**
     * @throws RuntimeException if vnStat cannot be run or produces no output.
     */
    public function read(?string $interface = null): string
    {
        if (!function_exists('shell_exec')) {
            throw new RuntimeException(
                'shell_exec() is disabled in this PHP installation, so vnStat cannot be run.'
            );
        }

        $command = 'vnstat --json';

        if (null !== $interface) {
            $command .= sprintf(' --iface %s', $interface);
        }

        $output = shell_exec($command);

        if (!is_string($output) || '' === trim($output)) {
            throw new RuntimeException(sprintf(
                'Running "%s" produced no output. Check that vnStat is installed and that the web '
                . 'server user can run it.',
                $command
            ));
        }

        return $output;
    }
}
