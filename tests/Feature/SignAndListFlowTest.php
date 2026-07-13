<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Config\Factories;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ContentSecurityPolicy;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use Config\Database;
use Config\Filters;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

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
 *
 * Coverage attribution (RECOMP v3): this is a full-stack integration test —
 * every request drives the controller, the repository adapter, AND the view
 * templates it renders end-to-end, asserting their real output (escaping,
 * validation, CSP, accessibility, banners, ordering). It therefore carries
 * NO #[CoversClass] restriction: PHPUnit narrows a test's recorded coverage
 * to its declared covered units, and the views this test genuinely exercises
 * have no class to declare — a narrow CoversClass silently discarded all of
 * that executed template code, understating line coverage to ~22%. Unit-level
 * tests keep their focused CoversClass / CoversNothing metadata; an integration
 * test that spans the whole slice legitimately covers the whole slice.
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

    #[Test]
    #[TestDox('Given a valid submission, when it is posted, then it is stored and acknowledged')]
    public function validSubmissionIsStoredAndAcknowledged(): void
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

    #[Test]
    #[TestDox('Given a submission padded with whitespace and HTML tags, when it is signed, then the stored fields are trimmed and tag-stripped (write-shape)')]
    public function submissionIsStoredTrimmedAndTagStripped(): void
    {
        $this->withoutCsrfFilter();

        $result = $this->post('Guestbook/create', [
            'name'    => '  Ada <b>Lovelace</b>  ',
            'email'   => 'ada@example.com',
            'message' => '  Hello <script>alert(1)</script> there  ',
        ]);

        $result->assertStatus(200);
        $this->assertSame(1, $this->countMessages());

        $stored = $this->conn->table('messages')->get();
        $this->assertNotFalse($stored);
        $rows = $stored->getResultArray();
        $this->assertSame('Ada Lovelace', $rows[0]['name'], 'the name is trimmed and tag-stripped before storage');
        $this->assertSame('Hello alert(1) there', $rows[0]['message'], 'the message is trimmed and tag-stripped before storage');
    }

    #[Test]
    #[TestDox('Given no CSRF token, when a submission is posted, then it is rejected and nothing is stored (GB2-02)')]
    public function tokenlessPostIsRejected(): void
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

    #[Test]
    #[TestDox('Given stored HTML metacharacters, when the timeline renders, then they are escaped (GB2-01)')]
    public function storedHtmlIsEscapedInTimeline(): void
    {
        $payload = 'Fish & "Chips" > everyone';
        $this->seedMessage('Escaper', 'esc@example.com', $payload, '2021-06-01 09:30:00');

        $result = $this->get('/');

        $result->assertStatus(200);
        $body = $this->body($result);
        $this->assertStringContainsString('Fish &amp; &quot;Chips&quot; &gt; everyone', $body, 'GB2-01: output must be escaped');
        $this->assertStringNotContainsString($payload, $body, 'GB2-01: the raw metacharacter payload must never render');
    }

    #[Test]
    #[TestDox('Given a stored received_on, when the timeline renders, then it shows the stored date, not "now" (GB2-03)')]
    public function timelineShowsStoredReceivedOn(): void
    {
        $this->seedMessage('Old Timer', 'old@example.com', 'A message from the past.', '2020-02-14 08:15:00');

        $result = $this->get('/');

        $result->assertStatus(200);
        $body = $this->body($result);
        $this->assertStringContainsString('14-02-20', $body, 'GB2-03: the stored date must render');
        $this->assertStringContainsString('08:15 am', $body, 'GB2-03: the stored time must render');
        $this->assertStringNotContainsString(date('d-m-y'), $body, 'GB2-03: "now" must not replace the stored date');
    }

    #[Test]
    #[TestDox('Given messages with different received_on, when the timeline renders, then the newest is listed first')]
    public function messagesAreListedNewestFirst(): void
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

    #[Test]
    #[TestDox('Given no messages, when the homepage renders, then the timeline section is hidden entirely')]
    public function emptyTimelineIsHidden(): void
    {
        $result = $this->get('/');

        $result->assertStatus(200);
        $this->assertStringNotContainsString('Previous Messages', $this->body($result));
    }

    /**
     * @param array<string, string> $post
     */
    #[Test]
    #[TestDox("Given the '\$_dataName' case, when the submission is posted, then an inline error is shown and nothing is stored")]
    #[DataProvider('provideInvalidSubmissions')]
    public function validationErrorsAreShownInline(array $post, string $errorNeedle): void
    {
        $this->withoutCsrfFilter();
        $result = $this->post('Guestbook/create', $post);
        $body   = $this->body($result);

        $this->assertSame(200, $result->response()->getStatusCode());
        $this->assertSame(0, $this->countMessages(), 'no row must be inserted on validation failure');
        $this->assertStringContainsString($errorNeedle, $body, 'an inline validation error must be shown');
        $this->assertStringContainsString('help-block has-error', $body, "the error must use the controller's error delimiters");
    }

    /**
     * @return iterable<string, array{0: array<string, string>, 1: string}>
     */
    public static function provideInvalidSubmissions(): iterable
    {
        yield 'short name' => [
            ['name' => 'Al', 'email' => 'valid@example.com', 'message' => 'Long enough message.'],
            'must be at least 3 characters',
        ];

        yield 'invalid email' => [
            ['name' => 'Valid Name', 'email' => 'not-an-email', 'message' => 'Long enough message.'],
            'must contain a valid email address',
        ];

        yield 'short message' => [
            ['name' => 'Valid Name', 'email' => 'valid@example.com', 'message' => 'Hi'],
            'must be at least 5 characters',
        ];
    }

    #[Test]
    #[TestDox('Given the rendered homepage, when its asset references are resolved, then each maps to a real file under public/ (GB2-10)')]
    public function referencedAssetsMapToPublicFiles(): void
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

    #[Test]
    #[TestDox('Given the persistence outcome, when the page renders, then the success banner shows only on a real write and the honest error copy shows on failure (GB2-FEAT-01/02)')]
    public function bannerReflectsPersistenceOutcome(): void
    {
        helper('form');

        $persisted = (string) view('guestbook_homepage', ['messages' => [], 'valid' => true, 'errors' => []]);
        $this->assertStringContainsString('Your message has been processed', $persisted, 'a real write shows the success banner');
        $this->assertStringNotContainsString('could not be saved', $persisted, 'no error banner on success');

        $failed = (string) view('guestbook_homepage', ['messages' => [], 'valid' => false, 'errors' => []]);
        $this->assertStringContainsString(
            'Your message could not be saved. Please try again.',
            $failed,
            'GB2-FEAT-01/02: a failed write shows the honest error copy',
        );
        $this->assertStringNotContainsString('has been processed', $failed, 'no success banner on failure');

        // Before any submission (valid is null), neither banner is shown.
        $neutral = (string) view('guestbook_homepage', ['messages' => [], 'valid' => null, 'errors' => []]);
        $this->assertStringNotContainsString('has been processed', $neutral);
        $this->assertStringNotContainsString('could not be saved', $neutral);
    }

    #[Test]
    #[TestDox("Given the homepage response, when its headers are read, then a Content-Security-Policy pins script-src to 'self' with no unsafe-inline (GB2-FEAT-04)")]
    public function responseCarriesHardenedCspHeader(): void
    {
        $result = $this->get('/');
        $result->assertStatus(200);

        // In-process feature requests return the response WITHOUT the send()
        // step, which is where the framework builds the CSP header. Run that
        // exact step here so we assert the header production would emit.
        $csp = service('csp');
        $this->assertNotNull($csp, 'the CSP service must resolve');
        assert($csp instanceof ContentSecurityPolicy);
        $this->assertTrue($csp->enabled(), 'CSP must be enabled (App::$CSPEnabled = true)');

        $response = $result->response();
        $csp->finalize($response);

        $header = $response->getHeaderLine('Content-Security-Policy');
        $this->assertNotSame('', $header, 'the response must carry a Content-Security-Policy header');
        $this->assertMatchesRegularExpression("/script-src[^;]*'self'/", $header, "script-src must allow 'self'");
        $this->assertMatchesRegularExpression("/default-src[^;]*'self'/", $header, "default-src must allow 'self'");
        $this->assertMatchesRegularExpression("/style-src[^;]*'self'/", $header, "style-src must allow 'self'");
        $this->assertStringNotContainsString("'unsafe-inline'", $header, "GB2-FEAT-04: no 'unsafe-inline' may weaken the policy");
    }

    #[Test]
    #[TestDox('Given the homepage markup, when it renders, then the logo has alt text, form inputs have matching labels, and pinch-zoom is enabled (GB2-FEAT-03)')]
    public function homepageMarkupIsAccessible(): void
    {
        $body = $this->body($this->get('/'));

        // The logo carries non-empty alt text.
        $this->assertMatchesRegularExpression(
            '/<img[^>]*\balt="[^"]+"[^>]*>/',
            $body,
            'GB2-FEAT-03: the logo image must have non-empty alt text',
        );

        // Every form field has a <label for> that matches its input id.
        foreach (['name', 'email', 'message'] as $field) {
            $this->assertMatchesRegularExpression(
                '/<label[^>]*\bfor="' . $field . '"/',
                $body,
                "GB2-FEAT-03: the {$field} field must have a matching <label for>",
            );
            $this->assertStringContainsString('id="' . $field . '"', $body, "GB2-FEAT-03: the {$field} input must carry the label's id");
        }

        // Pinch-zoom stays available — the viewport must not disable scaling.
        $this->assertStringNotContainsString('user-scalable=no', $body, 'GB2-FEAT-03: pinch-zoom must remain enabled');
        $this->assertStringNotContainsString('maximum-scale=1', $body, 'GB2-FEAT-03: the viewport must not cap zoom');
    }

    #[Test]
    #[TestDox('Given the externalized init script, when the homepage renders, then it is loaded from public/ and no inline script remains (GB2-FEAT-04)')]
    public function initScriptIsExternalized(): void
    {
        $body = $this->body($this->get('/'));

        $this->assertMatchesRegularExpression(
            '/<script[^>]*\bsrc="[^"]*js\/guestbook-init\.js"/',
            $body,
            'GB2-FEAT-04: the init logic must load from an external file',
        );

        // No inline <script> with a body — this is what lets CSP drop
        // 'unsafe-inline' without breaking the page.
        $this->assertDoesNotMatchRegularExpression(
            '/<script(?![^>]*\bsrc=)[^>]*>\s*\S/',
            $body,
            "GB2-FEAT-04: no inline script may remain once CSP forbids 'unsafe-inline'",
        );
    }

    #[Test]
    #[TestDox('Given a validation failure, when the inline error renders, then it is associated to the field for assistive tech (GB2-FEAT-03)')]
    public function validationErrorsAreAssociatedForAssistiveTech(): void
    {
        $this->withoutCsrfFilter();

        $result = $this->post('Guestbook/create', [
            'name'    => 'Al',
            'email'   => 'valid@example.com',
            'message' => 'Long enough message.',
        ]);
        $body = $this->body($result);

        $this->assertMatchesRegularExpression(
            '/<span[^>]*\bid="name-error"[^>]*\brole="alert"/',
            $body,
            'GB2-FEAT-03: the inline error must carry a unique id and role="alert"',
        );
    }

    #[Test]
    #[TestDox('Given a populated guestbook, when the homepage renders, then it carries the document title and shows the timeline section heading')]
    public function homepageRendersTitleAndPopulatedTimelineHeading(): void
    {
        $body = $this->body($this->get('/'));
        $this->assertStringContainsString('<title>Guest Book Test App</title>', $body, 'the document metadata must render its title');
        $this->assertStringNotContainsString('Previous Messages', $body, 'the timeline heading is hidden while the guestbook is empty');

        $this->seedMessage('Grace Hopper', 'grace@example.com', 'A signed message.', '2021-03-04 10:00:00');
        $populated = $this->body($this->get('/'));

        $this->assertStringContainsString('Previous Messages', $populated, 'the timeline section heading appears once a message exists');
        $this->assertStringContainsString('<a href="#">Grace Hopper</a>', $populated, 'the stored author renders inside the timeline entry');
        $this->assertStringContainsString('04-03-21', $populated, 'the stored received_on date renders in the timeline');
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
