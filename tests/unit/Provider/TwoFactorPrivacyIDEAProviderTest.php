<?php

namespace Tests\Unit\Provider;

use OCA\TwoFactor_privacyIDEA\Provider\AdminAuthException;
use OCA\TwoFactor_privacyIDEA\Provider\TwoFactorPrivacyIDEAProvider;
use OCP\Authentication\TwoFactorAuth\TwoFactorException;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the provider with a fake HTTP client, session, logger and configuration.
 * The tests run without ownCloud (see tests/unit/stubs/ocp.php).
 */
class TwoFactorPrivacyIDEAProviderTest extends TestCase
{
    private const NO_USER = '{"result":{"status":false,"error":{"code":904,"message":"ERR904: The user can not be found in any resolver in this realm!"}}}';
    private const NO_TOKEN = '{"result":{"status":false,"error":{"code":905,"message":"ERR905: The user has no tokens assigned"}}}';
    private const AUTH_OK = '{"result":{"status":true,"value":{"token":"jwt-token","rights":["triggerchallenge"]}},"versionnumber":"3.9"}';
    private const MATCH = '{"detail":{"message":"matching 1 tokens"},"result":{"status":true,"value":true}}';
    private const WRONG_OTP = '{"detail":{"message":"wrong otp pin"},"result":{"status":true,"value":false}}';
    private const CHALLENGE = '{"result":{"status":true,"value":1},"detail":{"message":"please enter otp","transaction_id":"1234","multi_challenge":[{"client_mode":"interactive","type":"hotp"}]}}';

    /** @var FakeClient */
    private $client;
    /** @var FakeSession */
    private $session;
    /** @var FakeLogger */
    private $logger;
    /** @var FakeConfig */
    private $config;
    /** @var FakeRequest */
    private $request;
    /** @var FakeGroupManager */
    private $groups;
    /** @var TwoFactorPrivacyIDEAProvider */
    private $provider;

    protected function setUp(): void
    {
        $this->client = new FakeClient();
        $this->session = new FakeSession();
        $this->logger = new FakeLogger();
        $this->config = new FakeConfig(["url" => "http://pi.test", "piactive" => "1"]);
        $this->request = new FakeRequest();
        $this->groups = new FakeGroupManager();
        $this->provider = new TwoFactorPrivacyIDEAProvider($this->client, $this->config, $this->logger,
            $this->request, $this->groups, new FakeL10N(), $this->session);
    }

    private function response(int $status, string $body): FakeResponse
    {
        return new FakeResponse($status, $body);
    }

    private function verify(string $uid, string $otp)
    {
        return $this->provider->verifyChallenge(new FakeUser($uid), $otp);
    }

    /** Run verifyChallenge and return the message of the TwoFactorException. */
    private function failureOf(string $uid, string $otp): string
    {
        try
        {
            $this->verify($uid, $otp);
        }
        catch (TwoFactorException $e)
        {
            return $e->getMessage();
        }
        $this->fail("verifyChallenge did not throw a TwoFactorException.");
    }

    // ---- verifyChallenge: the answer of privacyIDEA

    public function testCorrectOtpLogsInAndRemovesTheLoginState(): void
    {
        $this->session->set("pi_message", "old");
        $this->client->queue($this->response(200, self::MATCH));

        $this->assertTrue($this->verify("alice", "123456"));
        $this->assertSame([], $this->session->data, "The state of the login must not stay in the session.");
        $this->assertSame("http://pi.test/validate/check", $this->client->requests[0]["uri"]);
    }

    public function testWrongOtpShowsTheMessageOfPrivacyIdea(): void
    {
        $this->client->queue($this->response(200, self::WRONG_OTP));

        $this->assertSame("wrong otp pin", $this->failureOf("alice", "000000"));
    }

    public function testUnknownUserWithoutPassOnNoUserShowsTheError(): void
    {
        $this->client->queue($this->response(400, self::NO_USER));

        $this->assertStringContainsString("ERR904", $this->failureOf("dave", "000000"));
        $this->assertNull($this->session->get("pi_noAuthRequired"));
    }

    public function testAnAnswerThatIsNotJsonGivesAShortMessage(): void
    {
        $this->client->queue($this->response(502, "<html><body>Bad gateway</body></html>"));

        $this->assertSame("Unexpected answer from privacyIDEA.", $this->failureOf("alice", "000000"));
    }

    public function testAnErrorOfTheHttpClientDoesNotReachTheUser(): void
    {
        $this->client->queue(new \RuntimeException(
            "cURL error 6: Could not resolve host: pi.internal.example for http://pi.internal.example/validate/check"));

        $message = $this->failureOf("alice", "000000");

        $this->assertSame("Failed to communicate with privacyIDEA.", $message);
        $this->assertStringContainsString("pi.internal.example", $this->logger->all(), "The log keeps the detail.");
    }

    public function testATypeErrorDoesNotEndInAnInternalServerError(): void
    {
        $this->client->queue(new \TypeError("Unsupported operand types: string * int"));

        $this->assertSame("Failed to communicate with privacyIDEA.", $this->failureOf("alice", "000000"));
    }

    // ---- PassOnNoUser

    public function testPassOnNoUserLetsAnUnknownUserIn(): void
    {
        $this->config->values["passOnNoUser"] = "1";
        $this->client->queue($this->response(400, self::NO_USER));

        $this->assertSame("No token found for your user, Login is still enabled.", $this->failureOf("dave", "000000"));
        $this->assertTrue($this->session->get("pi_noAuthRequired"));

        // The page sends the form again. This time privacyIDEA is not asked.
        $this->assertTrue($this->verify("dave", "000000"));
        $this->assertCount(1, $this->client->requests);
        $this->assertSame([], $this->session->data);
    }

    public function testPassOnNoUserDoesNotHelpAUserThatPrivacyIdeaKnows(): void
    {
        $this->config->values["passOnNoUser"] = "1";
        $this->client->queue($this->response(200, self::WRONG_OTP));

        $this->assertSame("wrong otp pin", $this->failureOf("carol", "000000"));
        $this->assertNull($this->session->get("pi_noAuthRequired"));
    }

    public function testPassOnNoUserDoesNotHelpAUserWithoutTokenWhenTheErrorIsNot904(): void
    {
        $this->config->values["passOnNoUser"] = "1";
        $this->client->queue($this->response(400, self::NO_TOKEN));

        $this->assertStringContainsString("ERR905", $this->failureOf("carol", "000000"));
        $this->assertNull($this->session->get("pi_noAuthRequired"));
    }

    public function testThePassOnNoUserStateDoesNotPassToAnotherUser(): void
    {
        $this->config->values["passOnNoUser"] = "1";
        $this->client->queue($this->response(400, self::NO_USER));
        $this->failureOf("dave", "000000");
        $this->assertTrue($this->session->get("pi_noAuthRequired"));

        // The same session now belongs to another user. The old state must not skip the second factor.
        $this->client->queue($this->response(200, self::WRONG_OTP));
        $this->assertSame("wrong otp pin", $this->failureOf("alice", "000000"));

        $this->assertCount(2, $this->client->requests, "privacyIDEA must be asked for the second user.");
        $this->assertNull($this->session->get("pi_noAuthRequired"));
        $this->assertSame("alice", $this->session->get("pi_uid"));
    }

    // ---- getTemplate

    public function testGetTemplateRemovesTheStateOfAnotherUser(): void
    {
        $this->session->set("pi_uid", "dave");
        $this->session->set("pi_noAuthRequired", true);
        $this->session->set("pi_message", "message of dave");

        $template = $this->provider->getTemplate(new FakeUser("alice"));

        $this->assertNull($this->session->get("pi_noAuthRequired"));
        $this->assertSame("alice", $this->session->get("pi_uid"));
        $this->assertArrayNotHasKey("message", $template->vars);
    }

    public function testGetTemplateTriggersTheChallenge(): void
    {
        $this->config->values["triggerchallenges"] = "1";
        $this->client->queue($this->response(200, self::AUTH_OK))->queue($this->response(200, self::CHALLENGE));

        $template = $this->provider->getTemplate(new FakeUser("bob"));

        $this->assertSame("please enter otp", $template->vars["message"]);
        $this->assertSame("http://pi.test/auth", $this->client->requests[0]["uri"]);
        $this->assertSame("http://pi.test/validate/triggerchallenge", $this->client->requests[1]["uri"]);
        $this->assertSame("jwt-token", $this->client->requests[1]["options"]["headers"]["PI-Authorization"]);
    }

    public function testGetTemplateShowsAMessageWhenTheTriggerFails(): void
    {
        $this->config->values["triggerchallenges"] = "1";
        $this->client->queue(new \TypeError("Unsupported operand types: string * int"));

        $template = $this->provider->getTemplate(new FakeUser("bob"));

        $this->assertSame("Failed to communicate with privacyIDEA.", $template->vars["message"]);
        $this->assertFalse($template->vars["hideOTPField"], "The user must see the OTP field.");
    }

    public function testGetTemplateSetsTheAutoSubmitForPassOnNoUser(): void
    {
        $this->config->values["triggerchallenges"] = "1";
        $this->config->values["passOnNoUser"] = "1";
        $this->client->queue($this->response(200, self::AUTH_OK))->queue($this->response(400, self::NO_USER));

        $template = $this->provider->getTemplate(new FakeUser("dave"));

        $this->assertSame("No token found for your user, Login is still enabled.", $template->vars["message"]);
        $this->assertTrue($template->vars["autoSubmit"]);
    }

    // ---- The timeout of the HTTP client

    /**
     * @dataProvider timeoutProvider
     */
    public function testTheTimeoutIsAlwaysAPositiveNumber($setting, int $expected): void
    {
        if ($setting !== null)
        {
            $this->config->values["pitimeout"] = $setting;
        }
        $this->client->queue($this->response(200, self::MATCH));

        $this->provider->validateCheck("alice", "123456");

        $this->assertSame($expected, $this->client->requests[0]["options"]["timeout"]);
    }

    public function timeoutProvider(): array
    {
        return [
            'not set' => [null, 5],
            'a number' => ["12", 12],
            'empty (the admin cleared the field)' => ["", 5],
            'zero' => ["0", 5],
            'negative' => ["-3", 5],
            'text' => ["abc", 5],
        ];
    }

    public function testAnEmptyTimeoutDoesNotBreakTheChallengePage(): void
    {
        $this->config->values["pitimeout"] = "";
        $this->config->values["triggerchallenges"] = "1";
        $this->client->queue($this->response(200, self::AUTH_OK))->queue($this->response(200, self::CHALLENGE));

        $template = $this->provider->getTemplate(new FakeUser("bob"));

        $this->assertSame("please enter otp", $template->vars["message"]);
    }

    public function testTheOptionsOfTheHttpClient(): void
    {
        $this->client->queue($this->response(200, self::MATCH))->queue($this->response(200, self::MATCH));
        $this->provider->validateCheck("alice", "123456");
        $options = $this->client->requests[0]["options"];
        $this->assertFalse($options["http_errors"], "An HTTP error status must not throw.");
        $this->assertArrayNotHasKey("verify", $options, "The CA bundle of ownCloud decides.");
        $this->assertArrayNotHasKey("proxy", $options);

        $this->config->values["checkssl"] = "0";
        $this->config->values["noproxy"] = "1";
        $this->provider->validateCheck("alice", "123456");
        $options = $this->client->requests[1]["options"];
        $this->assertFalse($options["verify"]);
        $this->assertSame(["https" => "", "http" => ""], $options["proxy"]);
    }

    // ---- Push: the poll request

    public function testThePollRequestSendsTheTransactionInTheQueryString(): void
    {
        $this->request->params["mode"] = "push";
        $this->session->set("pi_uid", "alice"); // getTemplate does this before the user answers
        $this->session->set("pi_transactionId", "42");
        $this->client->queue($this->response(200, '{"result":{"status":true,"value":true}}'))
            ->queue($this->response(200, '{"result":{"status":true,"value":true},"detail":{}}'));

        $this->assertTrue($this->verify("alice", ""));

        $poll = $this->client->requests[0];
        $this->assertSame("GET", $poll["method"]);
        $this->assertSame("http://pi.test/validate/polltransaction", $poll["uri"]);
        $this->assertSame("42", $poll["options"]["query"]["transaction_id"]);
        $this->assertArrayNotHasKey("form_params", $poll["options"], "Guzzle 7 sends no body with GET.");
        $this->assertSame("POST", $this->client->requests[1]["method"]);
    }

    public function testAPushThatIsNotConfirmedYetDoesNotLogIn(): void
    {
        $this->request->params["mode"] = "push";
        $this->session->set("pi_uid", "alice"); // getTemplate does this before the user answers
        $this->session->set("pi_transactionId", "42");
        $this->session->set("pi_message", "Confirm the push message");
        $this->client->queue($this->response(200, '{"result":{"status":true,"value":false}}'));

        $this->assertSame("Confirm the push message", $this->failureOf("alice", ""));
        $this->assertCount(1, $this->client->requests, "No validate/check before the push is confirmed.");
    }

    // ---- Passkey

    /** The answer of /validate/triggerchallenge for a user with one passkey token (privacyIDEA 3.14). */
    private const PASSKEY_CHALLENGE = '{"detail":{"challenge":"CH","client_mode":"webauthn","message":"Please authenticate with your passkey!","multi_challenge":[{"challenge":"CH","client_mode":"webauthn","message":"Please authenticate with your passkey!","rpId":"localhost","serial":"PIPK1","transaction_id":"555","type":"passkey","userVerification":"preferred"}],"preferred_client_mode":"webauthn","rpId":"localhost","serial":"PIPK1","transaction_id":"555","type":"passkey","userVerification":"preferred"},"result":{"authentication":"CHALLENGE","status":true,"value":false}}';
    private const PASSKEY_ACCEPT = '{"detail":{"message":"Found matching challenge","serial":"PIPK1","username":"erin"},"result":{"authentication":"ACCEPT","status":true,"value":true}}';
    private const PASSKEY_REJECT = '{"detail":{"message":"Authentication failed."},"result":{"authentication":"REJECT","status":true,"value":false}}';
    private const PASSKEY_NOT_MEANT = '{"result":{"status":false,"error":{"code":403,"message":"The challenge 555 is not meant for the token PIPK2."}}}';
    private const PASSKEY_SIGNED = '{"credential_id":"cred1","authenticatorData":"AAAA","clientDataJSON":"BBBB","signature":"CCCC","userHandle":"DDDD"}';

    /** Trigger the challenge for a user and return the template. */
    private function templateAfterTrigger(string $uid, string $challengeBody)
    {
        $this->config->values["triggerchallenges"] = "1";
        $this->client->queue($this->response(200, self::AUTH_OK))->queue($this->response(200, $challengeBody));
        return $this->provider->getTemplate(new FakeUser($uid));
    }

    /** The browser answers the passkey challenge of the user. */
    private function answerWithPasskey(string $uid): void
    {
        $this->request->params["mode"] = "passkey";
        $this->request->params["passkeySignResponse"] = self::PASSKEY_SIGNED;
        $this->request->params["origin"] = "http://localhost:8080";
        $this->session->set("pi_uid", $uid); // getTemplate does this before the user answers
        $this->session->set("pi_transactionId", "555");
    }

    public function testAPasskeyChallengeStartsThePasskeyMode(): void
    {
        $template = $this->templateAfterTrigger("erin", self::PASSKEY_CHALLENGE);

        $this->assertSame("passkey", $template->vars["mode"]);
        $this->assertSame(
            ["challenge" => "CH", "rpId" => "localhost", "userVerification" => "preferred"],
            json_decode($template->vars["passkeyChallenge"], true));
        $this->assertArrayNotHasKey("webAuthnSignRequest", $template->vars, "A passkey has no WebAuthn sign request.");
        $this->assertSame("555", $template->vars["transactionId"]);
    }

    public function testAPasskeyEntryRaisesNoPhpWarning(): void
    {
        // The old code read attributes->webAuthnSignRequest of every entry with the client mode "webauthn".
        // A passkey entry has no attributes. That is a warning on PHP 8 and a notice on PHP 7.4.
        $warnings = [];
        set_error_handler(function ($number, $message) use (&$warnings)
        {
            $warnings[] = $message;
            return true;
        });
        try
        {
            $this->templateAfterTrigger("erin", self::PASSKEY_CHALLENGE);
        }
        finally
        {
            restore_error_handler();
        }

        $this->assertSame([], $warnings);
        $this->assertNull($this->session->get("pi_webAuthnSignRequest"));
    }

    public function testAWebAuthnTokenNextToAPasskeyKeepsTheWebAuthnMode(): void
    {
        $body = '{"detail":{"multi_challenge":['
            . '{"client_mode":"webauthn","type":"passkey","challenge":"CH","rpId":"localhost","userVerification":"preferred"},'
            . '{"client_mode":"webauthn","type":"webauthn","attributes":{"webAuthnSignRequest":{"challenge":"W"}}}],'
            . '"preferred_client_mode":"webauthn","transaction_id":"555"},"result":{"status":true,"value":false}}';

        $template = $this->templateAfterTrigger("erin", $body);

        $this->assertSame("webauthn", $template->vars["mode"]);
        $this->assertArrayHasKey("passkeyChallenge", $template->vars, "The passkey button stays available.");
        $this->assertArrayHasKey("webAuthnSignRequest", $template->vars);
    }

    public function testAPasskeyNextToAnOtpTokenStartsInTheOtpMode(): void
    {
        $body = '{"detail":{"multi_challenge":['
            . '{"client_mode":"interactive","type":"totp"},'
            . '{"client_mode":"webauthn","type":"passkey","challenge":"CH","rpId":"localhost","userVerification":"preferred"}],'
            . '"preferred_client_mode":"interactive","transaction_id":"555"},"result":{"status":true,"value":false}}';

        $template = $this->templateAfterTrigger("erin", $body);

        $this->assertSame("otp", $template->vars["mode"]);
        $this->assertArrayHasKey("passkeyChallenge", $template->vars);
    }

    public function testANewChallengeWithoutPasskeyRemovesTheOldPasskeyChallenge(): void
    {
        $this->templateAfterTrigger("erin", self::PASSKEY_CHALLENGE);
        $this->assertNotNull($this->session->get("pi_passkeyChallenge"));

        // The user enters a wrong OTP, and privacyIDEA answers with a new challenge for another token.
        $this->request->params["mode"] = "otp";
        $this->client->queue($this->response(200, self::CHALLENGE));
        $this->failureOf("erin", "000000");

        $this->assertNull($this->session->get("pi_passkeyChallenge"));
        $this->assertSame("1234", $this->session->get("pi_transactionId"));
    }

    public function testTheAnswerOfAPasskeyLogsIn(): void
    {
        $this->answerWithPasskey("erin");
        $this->client->queue($this->response(200, self::PASSKEY_ACCEPT));

        $this->assertTrue($this->verify("erin", ""));

        $this->assertSame([], $this->session->data, "The state of the login must not stay in the session.");
        $this->assertCount(1, $this->client->requests);
    }

    public function testTheRequestForAPasskeyHasTheUserTheTransactionAndTheOrigin(): void
    {
        $this->config->values["realm"] = "dev";
        $this->answerWithPasskey("erin");
        $this->client->queue($this->response(200, self::PASSKEY_ACCEPT));

        $this->verify("erin", "");

        $request = $this->client->requests[0];
        $this->assertSame("POST", $request["method"]);
        $this->assertSame("http://pi.test/validate/check", $request["uri"]);
        $this->assertSame([
            "user" => "erin",
            "transaction_id" => "555",
            "credential_id" => "cred1",
            "authenticatorData" => "AAAA",
            "clientDataJSON" => "BBBB",
            "signature" => "CCCC",
            "realm" => "dev",
            "userHandle" => "DDDD"], $request["options"]["form_params"],
            "The user makes privacyIDEA refuse the passkey of another user. There is no pass.");
        $this->assertSame("http://localhost:8080", $request["options"]["headers"]["Origin"]);
    }

    public function testAPasskeyOfAnotherUserIsRefused(): void
    {
        $this->answerWithPasskey("erin");
        $this->client->queue($this->response(200, self::PASSKEY_REJECT));

        $this->assertSame("Authentication failed.", $this->failureOf("erin", ""));
        $this->assertSame("otp", $this->session->get("pi_mode"), "A refused answer must not start the prompt again.");
    }

    public function testAnErrorForAPasskeyShowsTheMessageAndGoesBackToTheOtpMode(): void
    {
        $this->answerWithPasskey("erin");
        $this->client->queue($this->response(403, self::PASSKEY_NOT_MEANT));

        $this->assertStringContainsString("is not meant for the token", $this->failureOf("erin", ""));
        $this->assertSame("otp", $this->session->get("pi_mode"));
    }

    public function testAnIncompletePasskeyAnswerSendsNothing(): void
    {
        foreach (["credential_id", "authenticatorData", "clientDataJSON", "signature"] as $missing)
        {
            $this->setUp();
            $this->answerWithPasskey("erin");
            $answer = json_decode(self::PASSKEY_SIGNED, true);
            unset($answer[$missing]);
            $this->request->params["passkeySignResponse"] = json_encode($answer);

            $this->assertSame("Failed to authenticate.", $this->failureOf("erin", ""), "without " . $missing);
            $this->assertSame([], $this->client->requests, "without " . $missing);
        }
    }

    public function testAPasskeyAnswerWithoutOriginTransactionOrJsonSendsNothing(): void
    {
        $cases = [
            "no origin" => function () { $this->request->params["origin"] = ""; },
            "no transaction" => function () { $this->session->remove("pi_transactionId"); },
            "no json" => function () { $this->request->params["passkeySignResponse"] = "not json"; },
        ];
        foreach ($cases as $name => $change)
        {
            $this->setUp();
            $this->answerWithPasskey("erin");
            $change();

            $this->assertSame("Failed to authenticate.", $this->failureOf("erin", ""), $name);
            $this->assertSame([], $this->client->requests, $name);
        }
    }

    public function testACanceledPasskeyGoesBackToTheOtpMode(): void
    {
        // The page sends the mode "otp" with modeChanged when the user cancels the prompt of the browser.
        $this->session->set("pi_uid", "erin");
        $this->session->set("pi_message", "Please authenticate with your passkey!");
        $this->request->params["mode"] = "otp";
        $this->request->params["modeChanged"] = "1";

        $this->assertSame("Please authenticate with your passkey!", $this->failureOf("erin", ""));
        $this->assertSame("otp", $this->session->get("pi_mode"));
        $this->assertSame([], $this->client->requests);
    }

    public function testThePasskeyChallengeBelongsToOneUser(): void
    {
        $this->templateAfterTrigger("erin", self::PASSKEY_CHALLENGE);

        // Another user gets the page with the same session. The challenge of erin must be gone.
        $this->config->values["triggerchallenges"] = "0";
        $template = $this->provider->getTemplate(new FakeUser("alice"));

        $this->assertArrayNotHasKey("passkeyChallenge", $template->vars);
        $this->assertNull($this->session->get("pi_passkeyChallenge"));
    }

    // ---- The log

    public function testTheLogHasNoOtp(): void
    {
        $this->client->queue($this->response(200, self::MATCH));

        $this->verify("alice", "987654");

        $this->assertStringNotContainsString("987654", $this->logger->all());
        // http_build_query() encodes the three stars.
        $this->assertStringContainsString("pass=%2A%2A%2A", $this->logger->all());
    }

    public function testTheLogHasNoPasswordOfTheServiceAccount(): void
    {
        // The trace of an exception shows the arguments of every function when PHP keeps them.
        ini_set("zend.exception_ignore_args", "0");
        $error = null;
        try
        {
            $this->throwFromDeepInside("svc", "Secret12xyz");
        }
        catch (\RuntimeException $e)
        {
            $error = $e;
        }
        $this->assertStringContainsString("Secret12xyz", $error->getTraceAsString(), "The test needs a trace with arguments.");
        $this->client->queue($error);

        try
        {
            $this->provider->getAuthToken("svc", "Secret12xyz");
            $this->fail("getAuthToken did not throw.");
        }
        catch (AdminAuthException $e)
        {
            $this->assertSame("Failed to fetch authentication token.", $e->getMessage());
        }

        $this->assertStringNotContainsString("Secret12xyz", $this->logger->all());
    }

    private function throwFromDeepInside(string $user, string $password): void
    {
        throw new \RuntimeException("cURL error 7: Failed to connect");
    }

    // ---- isTwoFactorAuthEnabledForUser

    public function testTwoFactorIsOffWhenTheAppIsNotActive(): void
    {
        $this->config->values["piactive"] = "";
        $this->assertFalse($this->provider->isTwoFactorAuthEnabledForUser(new FakeUser("alice")));
    }

    public function testTwoFactorIsOnByDefault(): void
    {
        $this->assertTrue($this->provider->isTwoFactorAuthEnabledForUser(new FakeUser("alice")));
    }

    public function testAnExcludedIpAddressSkipsTheSecondFactor(): void
    {
        $this->config->values["piexcludeips"] = "10.0.0.0-10.0.0.255";

        $this->request->remoteAddress = "10.0.0.5";
        $this->assertFalse($this->provider->isTwoFactorAuthEnabledForUser(new FakeUser("alice")));

        $this->request->remoteAddress = "192.168.1.5";
        $this->assertTrue($this->provider->isTwoFactorAuthEnabledForUser(new FakeUser("alice")));
    }

    public function testExcludedGroups(): void
    {
        $this->config->values["piexcludegroups"] = "staff";
        $this->config->values["piexclude"] = "1";

        $this->groups->groups = ["staff"];
        $this->assertFalse($this->provider->isTwoFactorAuthEnabledForUser(new FakeUser("alice")), "In an excluded group.");

        $this->groups->groups = [];
        $this->assertTrue($this->provider->isTwoFactorAuthEnabledForUser(new FakeUser("alice")), "Not in the excluded group.");
    }

    public function testIncludedGroups(): void
    {
        $this->config->values["piexcludegroups"] = "staff";
        $this->config->values["piexclude"] = "0";

        $this->groups->groups = ["staff"];
        $this->assertTrue($this->provider->isTwoFactorAuthEnabledForUser(new FakeUser("alice")), "In an included group.");

        $this->groups->groups = [];
        $this->assertFalse($this->provider->isTwoFactorAuthEnabledForUser(new FakeUser("alice")), "Not in the included group.");
    }
}
