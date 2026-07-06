<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * The FrozenRuntimeContainerTest pinning + secret-hygiene assertions,
 * ported INTO the wired suite (PTAH MIG-09 — the CI3-era original was a
 * standalone script outside phpunit.xml). Live build/boot verification is
 * the CI pipeline's job; these assertions keep the pinning discipline
 * biting on every harness run.
 */
final class InfraPinningTest extends CIUnitTestCase
{
    public function test_all_images_are_pinned_to_explicit_non_latest_tags(): void
    {
        $dockerfile = file_get_contents(ROOTPATH . 'Dockerfile');
        $this->assertNotFalse($dockerfile);

        preg_match_all('/^\s*FROM\s+(\S+)/mi', $dockerfile, $from);
        $this->assertNotEmpty($from[1], 'Dockerfile declares no FROM statement');

        preg_match_all('/^\s*COPY\s+--from=(\S+)/mi', $dockerfile, $copyFrom);

        $compose = file_get_contents(ROOTPATH . 'docker-compose.yml');
        $this->assertNotFalse($compose);
        preg_match_all('/^\s*image:\s*(\S+)\s*$/mi', $compose, $images);
        $this->assertNotEmpty($images[1], 'docker-compose.yml declares no image: values');

        foreach (array_merge($from[1], $copyFrom[1], $images[1]) as $image) {
            $this->assertDoesNotMatchRegularExpression('/:latest\b/i', $image, "{$image} floats on :latest");
            $this->assertStringContainsString(':', $image, "{$image} is not pinned to an explicit tag");
        }
    }

    public function test_no_credential_literal_in_tracked_runtime_config(): void
    {
        foreach (['docker-compose.yml', 'Dockerfile', 'app/Config/Database.php'] as $file) {
            $content = file_get_contents(ROOTPATH . $file);
            $this->assertNotFalse($content);
            // The SEC-2 shape: a quoted non-empty, non-interpolated value
            // assigned to a password key.
            $this->assertDoesNotMatchRegularExpression(
                '/[\'"]password[\'"]\s*=>\s*[\'"][^\'"]+[\'"]/i',
                $content,
                "{$file} must not carry a password literal (SEC-2 stays retired)",
            );
            $this->assertDoesNotMatchRegularExpression(
                '/(MYSQL_ROOT_PASSWORD|DB_PASSWORD)\s*[:=]\s*["\'][^"\'$][^"\']*["\']/',
                $content,
                "{$file} must source credentials from the environment, never a literal",
            );
        }
    }

    public function test_env_file_is_ignored_by_git_and_docker(): void
    {
        $gitignore = (string) file_get_contents(ROOTPATH . '.gitignore');
        $dockerignore = (string) file_get_contents(ROOTPATH . '.dockerignore');
        $this->assertMatchesRegularExpression('/^\.env\r?$/m', $gitignore, '.env must stay git-ignored');
        $this->assertMatchesRegularExpression('/^\.env\r?$/m', $dockerignore, '.env must stay docker-ignored');
    }
}
