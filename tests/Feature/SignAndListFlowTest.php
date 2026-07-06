<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Config\Factories;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use Config\Database;
use Config\Filters;

/**
 * The tsk-003 characterization net, ported to CI4 in-process feature tests
 * (PTAH MIG-09). Same scenarios, same live MySQL, same assertions — the
 * separate php -S process of the CI3 era is replaced by FeatureTestTrait,
 * which is what makes these requests visible to the coverage driver.
 *
 * Asset HTTP resolution (the old test_assets_resolve_to_request_origin's
 * live fetches) is owned by the Playwright E2E gate; the in-process
 * equivalent here pins that every referenced asset maps to a real file
 * under public/.
 */
final class SignAndListFlowTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    /** @var BaseConnection<object|resource, object|resource> */
    private BaseConnection $conn;

    protected function setUp(): void
    {
        parent::setUp();
        $this->conn = Database::connect();
        $this->conn->table('messages')->truncate();
    }

    /**
     * The CSRF filter is validated by test_tokenless_post_is_rejected; the
     * remaining POST scenarios bypass it the same way the CI3 suite carried
     * a token — the subject under test is the flow, not the filter.
     */
    private function withoutCsrfFilter(): void
    {
        $filters = new Filters();
        $filters->globals['before'] = array_values(array_filter(
            $filters->globals['before'],
            static fn ($f) => $f !== 'csrf',
        ));
        Factories::injectMock('config', 'Filters', $filters);
    }

    private function body(TestResponse $result): string
    {
        return (string) $result->response()->getBody();
    }

    // Scenario: A valid submission is stored and acknowledged
    public function test_valid_submission_stored_and_acknowledged(): void
    {
        $this->withoutCsrfFilter();

        $result = $this->post('Guestbook/create', [
            'name'    => 'Ada Lovelace',
            'email'   => 'ada@example.com',
            'message' => 'Characterizing the sign flow before it changes.',
        ]);

        $result->assertStatus(200);
        $body = $this->body($result);
        $this->assertStringContainsString('Your message has been processed', $body);
        $this->assertSame(1, $this->countMessages());

        $result = $this->conn->table('messages')->get();
        $this->assertNotFalse($result);
        $rows = $result->getResultArray();
        $this->assertSame('Ada Lovelace', $rows[0]['name']);
        $this->assertSame('ada@example.com', $rows[0]['email']);
        $this->assertSame('Characterizing the sign flow before it changes.', $rows[0]['message']);

        $this->assertStringContainsString('Ada Lovelace', $body);
    }

    // Scenario: A tokenless POST is rejected (GB2-02)
    public function test_tokenless_post_is_rejected(): void
    {
        try {
            $result = $this->post('Guestbook/create', [
                'name'    => 'Grace Hopper',
                'email'   => 'grace@example.com',
                'message' => 'No CSRF token accompanies this submission.',
            ]);
            $this->assertNotSame(200, $result->response()->getStatusCode(), 'GB2-02: a tokenless POST must not succeed');
        } catch (SecurityException $e) {
            $this->assertSame(403, $e->getCode(), 'GB2-02: the CSRF rejection is a hard 403');
        }

        $this->assertSame(0, $this->countMessages(), 'GB2-02: a tokenless POST must be rejected, not stored');
    }

    // Scenario: Stored HTML metacharacters are escaped at render (GB2-01)
    public function test_stored_html_is_escaped_in_timeline(): void
    {
        $payload = 'Fish & "Chips" > everyone';
        $this->seedMessage('Escaper', 'esc@example.com', $payload, '2021-06-01 09:30:00');

        $result = $this->get('/');

        $result->assertStatus(200);
        $body = $this->body($result);
        $this->assertStringContainsString('Fish &amp; &quot;Chips&quot; &gt; everyone', $body, 'GB2-01: output must be escaped');
        $this->assertStringNotContainsString($payload, $body, 'GB2-01: the raw metacharacter payload must never render');
    }

    // Scenario: Timeline renders the stored received_on, not "now" (GB2-03)
    public function test_timeline_shows_received_on(): void
    {
        $this->seedMessage('Old Timer', 'old@example.com', 'A message from the past.', '2020-02-14 08:15:00');

        $result = $this->get('/');

        $result->assertStatus(200);
        $body = $this->body($result);
        $this->assertStringContainsString('14-02-20', $body, 'GB2-03: the stored date must render');
        $this->assertStringContainsString('08:15 am', $body, 'GB2-03: the stored time must render');
        $this->assertStringNotContainsString(date('d-m-y'), $body, 'GB2-03: "now" must not replace the stored date');
    }

    // Scenario: Messages list newest first
    public function test_messages_listed_newest_first(): void
    {
        $this->seedMessage('Oldest', 'oldest@example.com', 'First message in.', '2020-01-01 08:00:00');
        $this->seedMessage('Newest', 'newest@example.com', 'Latest message in.', '2021-01-01 08:00:00');

        $body = $this->body($this->get('/'));

        $newestPos = strpos($body, 'Newest');
        $oldestPos = strpos($body, 'Oldest');
        $this->assertNotFalse($newestPos);
        $this->assertNotFalse($oldestPos);
        $this->assertLessThan($oldestPos, $newestPos, 'timeline must be received_on DESC');
    }

    // Scenario: An empty timeline section is hidden entirely
    public function test_empty_timeline_hidden(): void
    {
        $result = $this->get('/');

        $result->assertStatus(200);
        $this->assertStringNotContainsString('Previous Messages', $this->body($result));
    }

    // Scenario: Invalid submissions show inline errors and store nothing
    public function test_validation_errors_inline(): void
    {
        $cases = [
            'short name' => [
                'post'        => ['name' => 'Al', 'email' => 'valid@example.com', 'message' => 'Long enough message.'],
                'errorNeedle' => 'must be at least 3 characters',
            ],
            'invalid email' => [
                'post'        => ['name' => 'Valid Name', 'email' => 'not-an-email', 'message' => 'Long enough message.'],
                'errorNeedle' => 'must contain a valid email address',
            ],
            'short message' => [
                'post'        => ['name' => 'Valid Name', 'email' => 'valid@example.com', 'message' => 'Hi'],
                'errorNeedle' => 'must be at least 5 characters',
            ],
        ];

        foreach ($cases as $label => $case) {
            $this->withoutCsrfFilter();
            $result = $this->post('Guestbook/create', $case['post']);
            $body   = $this->body($result);

            $this->assertSame(200, $result->response()->getStatusCode(), $label);
            $this->assertSame(0, $this->countMessages(), $label . ': no row must be inserted on validation failure');
            $this->assertStringContainsString($case['errorNeedle'], $body, $label . ': an inline validation error must be shown');
            $this->assertStringContainsString('help-block has-error', $body, $label . ": the error must use the controller's error delimiters");
        }
    }

    // Scenario: Every referenced asset maps to a real file under public/
    public function test_assets_map_to_public_files(): void
    {
        $body = $this->body($this->get('/'));

        preg_match_all('/(?:href|src)="([^"]+\.(?:css|js))"/', $body, $matches);
        $this->assertNotEmpty($matches[1], 'the page must reference stylesheets/scripts');

        foreach ($matches[1] as $url) {
            $path = parse_url($url, PHP_URL_PATH);
            $this->assertFileExists(
                ROOTPATH . 'public' . $path,
                "referenced asset {$url} must exist under public/ (GB2-10 stays fixed)",
            );
        }
    }

    private function seedMessage(string $name, string $email, string $message, string $receivedOn): void
    {
        $this->conn->table('messages')->insert([
            'name'        => $name,
            'email'       => $email,
            'message'     => $message,
            'received_on' => $receivedOn,
        ]);
    }

    private function countMessages(): int
    {
        return (int) $this->conn->table('messages')->countAllResults();
    }
}
