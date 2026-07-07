<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * The FrozenRuntimeContainerTest pinning + secret-hygiene assertions,
 * ported INTO the wired suite (PTAH MIG-09 — the CI3-era original was a
 * standalone script outside phpunit.xml). Live build/boot verification is
 * the CI pipeline's job; these assertions keep the pinning discipline
 * biting on every harness run.
 *
 * These are static-config guarantees over tracked infra files — they
 * exercise no application class, so the suite covers nothing.
 */
#[CoversNothing]
final class InfraPinningTest extends CIUnitTestCase
{
    #[Test]
    #[TestDox('Given the tracked infra files, when their image references are read, then every image is pinned to an explicit non-latest tag')]
    public function allImagesArePinnedToExplicitNonLatestTags(): void
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

    #[Test]
    #[TestDox('Given the tracked runtime config, when it is scanned, then it carries no credential literal (SEC-2 stays retired)')]
    public function noCredentialLiteralInTrackedRuntimeConfig(): void
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

    #[Test]
    #[TestDox('Given the ignore files, when they are read, then .env stays ignored by both git and docker')]
    public function envFileIsIgnoredByGitAndDocker(): void
    {
        $gitignore = (string) file_get_contents(ROOTPATH . '.gitignore');
        $dockerignore = (string) file_get_contents(ROOTPATH . '.dockerignore');
        $this->assertMatchesRegularExpression('/^\.env\r?$/m', $gitignore, '.env must stay git-ignored');
        $this->assertMatchesRegularExpression('/^\.env\r?$/m', $dockerignore, '.env must stay docker-ignored');
    }
}
