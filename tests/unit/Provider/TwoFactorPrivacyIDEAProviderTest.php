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
        // The passkey button asks privacyIDEA for a challenge. Most tests do not queue that answer, so it is off here.
        // The tests in the section "The passkey button" turn it on.
        $this->config = new FakeConfig(["url" => "http://pi.test", "piactive" => "1", "passkeybutton" => "0"]);
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

    public function testASuccessWithoutDetailsLogsIn(): void
    {
        // The policy no_detail_on_success removes "detail" from a successful answer.
        $this->client->queue($this->response(200, '{"result":{"status":true,"value":true}}'));

        $this->assertTrue($this->verify("alice", "123456"));
    }

    /** A challenge with the header and the footer of the policies challenge_text_header and challenge_text_footer. */
    private const CHALLENGE_WITH_HEADER = '{"detail":{"message":"These are your options:<ul><li>please enter otp: </li>\n</ul>.<b>Authenticate &amp; go!</b>",'
        . '"messages":["please enter otp: "],"transaction_id":"1234","multi_challenge":[{"client_mode":"interactive","type":"hotp"}]},'
        . '"result":{"status":true,"value":false}}';

    public function testTheChallengeTextOfACheckIsPlainTextWithHeaderAndFooter(): void
    {
        $this->client->queue($this->response(200, self::CHALLENGE_WITH_HEADER));

        $this->assertSame("These are your options: please enter otp: . Authenticate & go!", $this->failureOf("alice", "1234"));
    }

    public function testTheChallengeTextOfATriggerIsPlainText(): void
    {
        $this->config->values["triggerchallenges"] = "1";
        $this->client->queue($this->response(200, self::AUTH_OK))->queue($this->response(200, self::CHALLENGE_WITH_HEADER));

        $template = $this->provider->getTemplate(new FakeUser("alice"));

        $this->assertSame("These are your options: please enter otp: . Authenticate & go!", $template->vars["message"]);
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

    public function testPassOnNoUserWorksWithTheStatusOfHideAuthErrorStatus(): void
    {
        // The policy hide_auth_error_status (privacyIDEA 3.14) answers every authentication error with HTTP 401.
        $this->config->values["passOnNoUser"] = "1";
        $this->client->queue($this->response(401, self::NO_USER));

        $this->assertSame("No token found for your user, Login is still enabled.", $this->failureOf("dave", "000000"));
        $this->assertTrue($this->session->get("pi_noAuthRequired"));
    }

    public function testPassOnNoUserIgnores904WithAServerError(): void
    {
        $this->config->values["passOnNoUser"] = "1";
        $this->client->queue($this->response(500, self::NO_USER));

        $this->failureOf("dave", "000000");
        $this->assertNull($this->session->get("pi_noAuthRequired"));
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

    public function testTheTriggerSendsTheRealmOnlyWhenItIsSet(): void
    {
        // An empty realm makes privacyIDEA ignore the realm in a user name like bob@sales.
        $this->config->values["triggerchallenges"] = "1";
        $this->client->queue($this->response(200, self::AUTH_OK))->queue($this->response(200, self::CHALLENGE));
        $this->provider->getTemplate(new FakeUser("bob@sales"));
        $this->assertSame(["user" => "bob@sales"], $this->client->requests[1]["options"]["form_params"]);

        $this->setUp();
        $this->config->values["triggerchallenges"] = "1";
        $this->config->values["realm"] = "dev";
        $this->client->queue($this->response(200, self::AUTH_OK))->queue($this->response(200, self::CHALLENGE));
        $this->provider->getTemplate(new FakeUser("bob"));
        $this->assertSame(["user" => "bob", "realm" => "dev"], $this->client->requests[1]["options"]["form_params"]);
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

    public function testARefusalOfPrivacyIdeaShowsItsMessage(): void
    {
        // The fake server refuses the answer here. The check of the user of an accepted answer is in the tests of
        // the passkey button.
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

    // ---- The Origin header of a WebAuthn or passkey answer

    public function testAPasskeyAnswerFromAnotherOriginIsRefused(): void
    {
        // privacyIDEA compares the Origin header with the origin that the browser signed. If the app forwards the
        // origin of the browser, a page on another host under the same relying party id can relay a login.
        $this->answerWithPasskey("erin");
        $this->request->params["origin"] = "https://evil.example.com";

        $this->assertSame("Failed to authenticate.", $this->failureOf("erin", ""));
        $this->assertSame([], $this->client->requests);
        $this->assertStringContainsString("overwriteprotocol", $this->logger->all(), "The log tells the admin what to set.");
    }

    public function testAWebAuthnAnswerFromAnotherOriginIsRefused(): void
    {
        $this->session->set("pi_uid", "erin");
        $this->session->set("pi_transactionId", "555");
        $this->request->params["mode"] = "webauthn";
        $this->request->params["webAuthnSignResponse"] = '{"signaturedata":"s","clientdata":"c","credentialid":"i","authenticatordata":"a"}';
        $this->request->params["origin"] = "https://evil.example.com";

        $this->assertSame("Failed to authenticate.", $this->failureOf("erin", ""));
        $this->assertSame([], $this->client->requests);
    }

    public function testTheOriginHeaderIsTheOriginOfOwnCloud(): void
    {
        // Behind a proxy, ownCloud knows its address from overwriteprotocol and overwritehost.
        $this->request->serverProtocol = "https";
        $this->request->serverHost = "Cloud.Example.com:443";
        $this->answerWithPasskey("erin");
        $this->request->params["origin"] = "https://cloud.example.com";
        $this->client->queue($this->response(200, self::PASSKEY_ACCEPT));

        $this->assertTrue($this->verify("erin", ""));
        $this->assertSame("https://cloud.example.com", $this->client->requests[0]["options"]["headers"]["Origin"]);
    }

    public function testAWebAuthnAnswerHasNoUserHandleAndNoExtensions(): void
    {
        // The encoding of the user handle depends on the version of privacyIDEA. Both fields are optional.
        $this->session->set("pi_uid", "erin");
        $this->session->set("pi_transactionId", "555");
        $this->request->params["mode"] = "webauthn";
        $this->request->params["webAuthnSignResponse"] = '{"signaturedata":"s","clientdata":"c","credentialid":"i",'
            . '"authenticatordata":"a","userhandle":"WAN0001","assertionclientextensions":"e30"}';
        $this->request->params["origin"] = "http://localhost:8080";
        $this->client->queue($this->response(200, self::MATCH));

        $this->assertTrue($this->verify("erin", ""));
        $form = $this->client->requests[0]["options"]["form_params"];
        $this->assertArrayNotHasKey("userhandle", $form);
        $this->assertArrayNotHasKey("assertionclientextensions", $form);
    }

    public function testEveryRequestHasTheUserAgentAlsoWithOwnHeaders(): void
    {
        $all = [];

        // The passkey answer has the header Origin. It must not replace the user agent.
        $this->answerWithPasskey("erin");
        $this->client->queue($this->response(200, self::PASSKEY_ACCEPT));
        $this->verify("erin", "");
        $all = array_merge($all, $this->client->requests);

        // The WebAuthn answer has the header Origin too.
        $this->setUp();
        $this->request->params["mode"] = "webauthn";
        $this->request->params["webAuthnSignResponse"] = '{"signaturedata":"s","clientdata":"c","credentialid":"i","authenticatordata":"a"}';
        $this->request->params["origin"] = "http://localhost:8080";
        $this->session->set("pi_uid", "erin");
        $this->session->set("pi_transactionId", "555");
        $this->client->queue($this->response(200, self::PASSKEY_ACCEPT));
        $this->verify("erin", "");
        $all = array_merge($all, $this->client->requests);

        // A request without own headers.
        $this->setUp();
        $this->client->queue($this->response(200, self::MATCH));
        $this->verify("alice", "123456");
        $all = array_merge($all, $this->client->requests);

        // The request for the token of the service account and the request of triggerchallenge.
        $this->setUp();
        $this->templateAfterTrigger("erin", self::PASSKEY_CHALLENGE);
        $all = array_merge($all, $this->client->requests);

        $this->assertCount(5, $all);
        foreach ($all as $request)
        {
            $this->assertSame("ownCloud Plugin", $request["options"]["headers"]["user-agent"], $request["uri"]);
        }
        $this->assertSame("http://localhost:8080", $all[0]["options"]["headers"]["Origin"], "The own header stays.");
        $this->assertSame("http://localhost:8080", $all[1]["options"]["headers"]["Origin"], "The own header stays.");
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

    // ---- The passkey button: a challenge from /validate/initialize

    private const INITIALIZE = '{"detail":{"passkey":{"challenge":"INIT","message":"Please authenticate with your passkey!","rpId":"localhost","transaction_id":"777","user_verification":"preferred"},"threadid":1,"transaction_id":"777"},"result":{"authentication":"CHALLENGE","status":true,"value":false}}';
    private const INITIALIZE_2 = '{"detail":{"passkey":{"challenge":"INIT2","rpId":"localhost","transaction_id":"888","user_verification":"required"},"transaction_id":"888"},"result":{"authentication":"CHALLENGE","status":true,"value":false}}';

    /** The setting is on by default, so remove the value that setUp() wrote. */
    private function turnOnThePasskeyButton(): void
    {
        unset($this->config->values["passkeybutton"]);
    }

    public function testThePasskeyButtonAsksForAChallengeWithoutAServiceAccount(): void
    {
        $this->turnOnThePasskeyButton();
        $this->config->values["realm"] = "dev";
        $this->client->queue($this->response(200, self::INITIALIZE));

        $template = $this->provider->getTemplate(new FakeUser("erin"));

        $this->assertCount(1, $this->client->requests, "There is no request for the token of a service account.");
        $request = $this->client->requests[0];
        $this->assertSame("POST", $request["method"]);
        $this->assertSame("http://pi.test/validate/initialize", $request["uri"]);
        $this->assertSame(["type" => "passkey", "realm" => "dev"], $request["options"]["form_params"]);
        $this->assertSame("ownCloud Plugin", $request["options"]["headers"]["user-agent"]);
        $this->assertSame(
            ["challenge" => "INIT", "rpId" => "localhost", "userVerification" => "preferred"],
            json_decode($template->vars["passkeyChallenge"], true),
            "The answer has user_verification. The page needs userVerification.");
    }

    public function testThePasskeyButtonDoesNotStartThePasskeyPrompt(): void
    {
        $this->turnOnThePasskeyButton();
        $this->client->queue($this->response(200, self::INITIALIZE));

        $template = $this->provider->getTemplate(new FakeUser("erin"));

        $this->assertNotSame("passkey", $template->vars["mode"] ?? "otp", "The user starts the prompt with the button.");
        $this->assertNull($this->session->get("pi_mode"));
        $this->assertNull($this->session->get("pi_transactionId"), "The transaction of the other challenges stays free.");
        $this->assertSame("777", $this->session->get("pi_passkeyTransactionId"));
    }

    public function testTheButtonShowsTheTextOfPrivacyIdea(): void
    {
        $this->turnOnThePasskeyButton();
        $this->client->queue($this->response(200, self::INITIALIZE));

        $template = $this->provider->getTemplate(new FakeUser("erin"));

        $this->assertSame("Please authenticate with your passkey!", $template->vars["passkeyMessage"]);
    }

    public function testACustomTextOfTheButtonIsPlainText(): void
    {
        // The policy passkey_challenge_text sets the text. It can hold HTML.
        $this->turnOnThePasskeyButton();
        $this->client->queue($this->response(200, str_replace("Please authenticate with your passkey!",
            "Use <b>your</b> key", self::INITIALIZE)));

        $template = $this->provider->getTemplate(new FakeUser("erin"));

        $this->assertSame("Use your key", $template->vars["passkeyMessage"]);
    }

    public function testWithoutATextTheButtonShowsTheLocalText(): void
    {
        $this->turnOnThePasskeyButton();
        $this->client->queue($this->response(200, self::INITIALIZE_2)); // has no message

        $template = $this->provider->getTemplate(new FakeUser("erin"));

        $this->assertSame("Log in with your passkey.", $template->vars["passkeyMessage"]);
    }

    public function testATriggeredPasskeyChallengeHasNoExtraText(): void
    {
        // The text of a triggered challenge is in the message of the page already.
        $this->turnOnThePasskeyButton();

        $template = $this->templateAfterTrigger("erin", self::PASSKEY_CHALLENGE);

        $this->assertArrayNotHasKey("passkeyMessage", $template->vars);
    }

    public function testTheSettingTurnsThePasskeyButtonOff(): void
    {
        $this->config->values["passkeybutton"] = "0";

        $template = $this->provider->getTemplate(new FakeUser("erin"));

        $this->assertSame([], $this->client->requests);
        $this->assertArrayNotHasKey("passkeyChallenge", $template->vars);
    }

    public function testNoPasskeyChallengeForAUserWhoNeedsNoSecondFactor(): void
    {
        $this->turnOnThePasskeyButton();
        $this->session->set("pi_uid", "dave");
        $this->session->set("pi_noAuthRequired", true);

        $template = $this->provider->getTemplate(new FakeUser("dave"));

        $this->assertSame([], $this->client->requests);
        $this->assertArrayNotHasKey("passkeyChallenge", $template->vars);
    }

    public function testATriggeredPasskeyChallengeStaysAndNothingIsInitialized(): void
    {
        $this->turnOnThePasskeyButton();

        $template = $this->templateAfterTrigger("erin", self::PASSKEY_CHALLENGE);

        $this->assertCount(2, $this->client->requests, "Only the token of the service account and the trigger.");
        $this->assertSame("CH", json_decode($template->vars["passkeyChallenge"], true)["challenge"]);
        $this->assertSame("555", $this->session->get("pi_passkeyTransactionId"));
        $this->assertNull($this->session->get("pi_passkeyFetchedAt"), "The challenge is not from /validate/initialize.");
        $this->assertNotNull($this->session->get("pi_passkeyTriggeredAt"), "The app knows the age of the challenge.");
    }

    public function testTheButtonNextToATriggeredOtpChallengeKeepsTheTransactionOfTheOtp(): void
    {
        $this->turnOnThePasskeyButton();
        $this->config->values["triggerchallenges"] = "1";
        $this->client->queue($this->response(200, self::AUTH_OK))
            ->queue($this->response(200, self::CHALLENGE))
            ->queue($this->response(200, self::INITIALIZE));

        $template = $this->provider->getTemplate(new FakeUser("bob"));

        $this->assertSame("INIT", json_decode($template->vars["passkeyChallenge"], true)["challenge"]);
        $this->assertSame("1234", $this->session->get("pi_transactionId"));

        // The user types the OTP. The request must carry the transaction of the OTP challenge.
        $this->client->queue($this->response(200, self::MATCH));
        $this->request->params["mode"] = "otp";
        $this->assertTrue($this->verify("bob", "123456"));
        $this->assertSame("1234", $this->client->requests[3]["options"]["form_params"]["transaction_id"]);
    }

    public function testWhenTheTriggerFailsPrivacyIdeaIsNotAskedAgain(): void
    {
        $this->turnOnThePasskeyButton();
        $this->config->values["triggerchallenges"] = "1";
        $this->client->queue(new \RuntimeException("cURL error 7: Failed to connect"));

        $template = $this->provider->getTemplate(new FakeUser("bob"));

        $this->assertCount(1, $this->client->requests);
        $this->assertArrayNotHasKey("passkeyChallenge", $template->vars);
    }

    public function testTheChallengeStaysUntilItIsOld(): void
    {
        $this->turnOnThePasskeyButton();
        $this->client->queue($this->response(200, self::INITIALIZE))->queue($this->response(200, self::INITIALIZE_2));

        $this->provider->getTemplate(new FakeUser("erin"));
        $template = $this->provider->getTemplate(new FakeUser("erin")); // the push page loads again and again
        $this->assertCount(1, $this->client->requests);
        $this->assertSame("INIT", json_decode($template->vars["passkeyChallenge"], true)["challenge"]);

        $this->session->set("pi_passkeyFetchedAt", time() - 61);
        $template = $this->provider->getTemplate(new FakeUser("erin"));
        $this->assertCount(2, $this->client->requests, "privacyIDEA drops the challenge after 120 seconds.");
        $this->assertSame("INIT2", json_decode($template->vars["passkeyChallenge"], true)["challenge"]);
        $this->assertSame("888", $this->session->get("pi_passkeyTransactionId"));
    }

    public function testTheAnswerGoesToTheTransactionOfTheInitializedChallenge(): void
    {
        $this->turnOnThePasskeyButton();
        $this->client->queue($this->response(200, self::INITIALIZE))->queue($this->response(200, self::PASSKEY_ACCEPT));
        $this->provider->getTemplate(new FakeUser("erin"));

        $this->request->params["mode"] = "passkey";
        $this->request->params["passkeySignResponse"] = self::PASSKEY_SIGNED;
        $this->request->params["origin"] = "http://localhost:8080";

        $this->assertTrue($this->verify("erin", ""));
        $form = $this->client->requests[1]["options"]["form_params"];
        $this->assertSame("777", $form["transaction_id"]);
        $this->assertSame("erin", $form["user"], "The user makes privacyIDEA refuse the passkey of another user.");
        $this->assertSame([], $this->session->data);
    }

    public function testAnInitializedChallengeIsGoodForOneAnswer(): void
    {
        $this->turnOnThePasskeyButton();
        $this->client->queue($this->response(200, self::INITIALIZE))
            ->queue($this->response(200, self::PASSKEY_REJECT))
            ->queue($this->response(200, self::INITIALIZE_2));
        $this->provider->getTemplate(new FakeUser("erin"));

        $this->request->params["mode"] = "passkey";
        $this->request->params["passkeySignResponse"] = self::PASSKEY_SIGNED;
        $this->request->params["origin"] = "http://localhost:8080";
        $this->assertSame("Authentication failed.", $this->failureOf("erin", ""));
        $this->assertNull($this->session->get("pi_passkeyChallenge"));
        $this->assertNull($this->session->get("pi_passkeyTransactionId"));

        // ownCloud shows the page again. The user gets a new challenge for the next try.
        $this->request->params = [];
        $template = $this->provider->getTemplate(new FakeUser("erin"));
        $this->assertSame("INIT2", json_decode($template->vars["passkeyChallenge"], true)["challenge"]);
    }

    public function testAnotherUserGetsHisOwnChallenge(): void
    {
        $this->turnOnThePasskeyButton();
        $this->client->queue($this->response(200, self::INITIALIZE))->queue($this->response(200, self::INITIALIZE_2));

        $this->provider->getTemplate(new FakeUser("erin"));
        $template = $this->provider->getTemplate(new FakeUser("alice")); // same session, other user

        $this->assertCount(2, $this->client->requests);
        $this->assertSame("INIT2", json_decode($template->vars["passkeyChallenge"], true)["challenge"]);
    }

    /**
     * @dataProvider unusableInitializeAnswers
     */
    public function testWithoutAUsableAnswerThePageHasNoButtonAndNoError($answer): void
    {
        $this->turnOnThePasskeyButton();
        $this->client->queue($answer);

        $template = $this->provider->getTemplate(new FakeUser("erin"));

        $this->assertArrayNotHasKey("passkeyChallenge", $template->vars);
        $this->assertArrayNotHasKey("message", $template->vars, "The user does not see an error for an extra.");
        $this->assertNull($this->session->get("pi_passkeyChallenge"));
        $this->assertNull($this->session->get("pi_passkeyFetchedAt"));
        $this->assertNotNull($this->session->get("pi_passkeyFailedAt"), "The app waits before it asks again.");
    }

    public function unusableInitializeAnswers(): array
    {
        return [
            "privacyIDEA before 3.11" => [new FakeResponse(400, '{"result":{"status":false,"error":{"code":905,"message":"ERR905: unknown type"}}}')],
            "no policy for the relying party" => [new FakeResponse(403, '{"result":{"status":false,"error":{"code":303,"message":"No relying party"}}}')],
            "an error page of a proxy" => [new FakeResponse(502, "<html>Bad gateway</html>")],
            "an empty challenge" => [new FakeResponse(200, '{"detail":{"passkey":{"challenge":"","transaction_id":"1"},"transaction_id":"1"},"result":{"status":true,"value":false}}')],
            "no transaction" => [new FakeResponse(200, '{"detail":{"passkey":{"challenge":"X"}},"result":{"status":true,"value":false}}')],
            "a challenge that is not text" => [new FakeResponse(200, '{"detail":{"passkey":{"challenge":["X"],"transaction_id":"1"},"transaction_id":"1"},"result":{"status":true,"value":false}}')],
            "no passkey in the answer" => [new FakeResponse(200, '{"detail":{"transaction_id":"1"},"result":{"status":true,"value":false}}')],
            "a client error" => [new \RuntimeException("cURL error 6: Could not resolve host: pi.internal.example")],
            "a type error" => [new \TypeError("Unsupported operand types: string * int")],
        ];
    }

    public function testAnErrorOfTheInitializeRequestStaysInTheLog(): void
    {
        $this->turnOnThePasskeyButton();
        $this->client->queue(new \RuntimeException("cURL error 6: Could not resolve host: pi.internal.example"));

        $template = $this->provider->getTemplate(new FakeUser("erin"));

        $this->assertStringContainsString("pi.internal.example", $this->logger->all());
        $this->assertArrayNotHasKey("message", $template->vars);
    }

    // ---- The user of an accepted passkey for the challenge of the button

    private const ACCEPT_FOR_BOB = '{"detail":{"message":"Found matching challenge","serial":"PIPK9","username":"bob"},"result":{"authentication":"ACCEPT","status":true,"value":true}}';
    private const ACCEPT_WITHOUT_DETAIL = '{"result":{"authentication":"ACCEPT","status":true,"value":true}}';

    /** Show the page with the challenge of the button, and let the browser answer it with a passkey. */
    private function answerTheButtonWithPasskey(string $uid, string $answerOfPrivacyIdea): void
    {
        $this->turnOnThePasskeyButton();
        $this->client->queue($this->response(200, self::INITIALIZE))->queue($this->response(200, $answerOfPrivacyIdea));
        $this->provider->getTemplate(new FakeUser($uid));
        $this->request->params["mode"] = "passkey";
        $this->request->params["passkeySignResponse"] = self::PASSKEY_SIGNED;
        $this->request->params["origin"] = "http://localhost:8080";
    }

    public function testThePasskeyOfAnotherUserDoesNotPassTheButton(): void
    {
        // The challenge of the button belongs to no user. privacyIDEA before 3.14 accepts the passkey of any user
        // for it and names the owner of the passkey. The app must compare the owner with the user who logs in.
        $this->answerTheButtonWithPasskey("alice", self::ACCEPT_FOR_BOB);

        $this->assertSame("Failed to authenticate.", $this->failureOf("alice", ""));
        $this->assertStringContainsString('belongs to the user "bob"', $this->logger->all());
        $this->assertSame("alice", $this->session->get("pi_uid"), "The login is not done.");
        $this->assertSame("otp", $this->session->get("pi_mode"));
        $this->assertNull($this->session->get("pi_passkeyChallenge"), "The next try gets a new challenge.");
    }

    public function testTheButtonRefusesASuccessWithoutUserName(): void
    {
        // The policy no_detail_on_success removes the user name. Then the app cannot check the user.
        $this->answerTheButtonWithPasskey("alice", self::ACCEPT_WITHOUT_DETAIL);

        $this->assertSame("Failed to authenticate.", $this->failureOf("alice", ""));
        $this->assertStringContainsString("has no user name", $this->logger->all());
    }

    public function testTheUserNameOfTheButtonMustBeEqualAlsoInCase(): void
    {
        $this->answerTheButtonWithPasskey("erin", str_replace('"username":"erin"', '"username":"Erin"', self::PASSKEY_ACCEPT));

        $this->assertSame("Failed to authenticate.", $this->failureOf("erin", ""));
    }

    public function testTheOwnerOfThePasskeyLogsInWithTheButton(): void
    {
        $this->answerTheButtonWithPasskey("erin", self::PASSKEY_ACCEPT);

        $this->assertTrue($this->verify("erin", ""));
        $this->assertSame([], $this->session->data);
    }

    public function testATriggeredChallengeNeedsNoUserNameInTheAnswer(): void
    {
        // privacyIDEA binds a triggered challenge to the passkeys of the user. The answer can be without details.
        $this->templateAfterTrigger("erin", self::PASSKEY_CHALLENGE);
        $this->request->params["mode"] = "passkey";
        $this->request->params["passkeySignResponse"] = self::PASSKEY_SIGNED;
        $this->request->params["origin"] = "http://localhost:8080";
        $this->client->queue($this->response(200, self::ACCEPT_WITHOUT_DETAIL));

        $this->assertTrue($this->verify("erin", ""));
    }

    /** The answer when privacyIDEA chains the enrollment of a HOTP token to the passkey (enroll_via_multichallenge). */
    private function enrollmentChainedToPasskeyOf(string $owner): string
    {
        return '{"detail":{"client_mode":"interactive","enroll_via_multichallenge":true,"image":"data:image/png;base64,QR",'
            . '"message":"Please scan the QR code and enter the OTP value!","messages":["Please scan the QR code and enter the OTP value!"],'
            . '"multi_challenge":[{"client_mode":"interactive","image":"data:image/png;base64,QR",'
            . '"message":"Please scan the QR code and enter the OTP value!","serial":"OATH1","transaction_id":"999","type":"hotp"}],'
            . '"serial":"OATH1","transaction_id":"999","type":"hotp","username":"' . $owner . '"},'
            . '"result":{"authentication":"CHALLENGE","status":true,"value":false}}';
    }

    public function testAnEnrollmentChainedToThePasskeyOfAnotherUserIsRefused(): void
    {
        $this->answerTheButtonWithPasskey("alice", $this->enrollmentChainedToPasskeyOf("bob"));

        $this->assertSame("Failed to authenticate.", $this->failureOf("alice", ""));
        $this->assertNull($this->session->get("pi_enrollImage"), "The page must not show the enrollment of bob.");
        $this->assertNull($this->session->get("pi_imgOTP"));
        $this->assertNull($this->session->get("pi_transactionId"));
    }

    public function testAnEnrollmentChainedToTheOwnPasskeyContinues(): void
    {
        $this->answerTheButtonWithPasskey("alice", $this->enrollmentChainedToPasskeyOf("alice"));

        $this->assertSame("Please scan the QR code and enter the OTP value!", $this->failureOf("alice", ""));
        $this->assertSame("data:image/png;base64,QR", $this->session->get("pi_enrollImage"), "It shows in every mode.");
        $this->assertSame("999", $this->session->get("pi_transactionId"), "The OTP of the new token answers this.");
    }

    public function testTheRealmOfThePasskeyMustBeTheRealmOfTheSettings(): void
    {
        // The policy add_resolver_in_response adds the realm of the owner to the answer.
        $this->config->values["realm"] = "dev";
        $answer = str_replace('"username":"erin"', '"username":"erin","user-realm":"other"', self::PASSKEY_ACCEPT);
        $this->answerTheButtonWithPasskey("erin", $answer);

        $this->assertSame("Failed to authenticate.", $this->failureOf("erin", ""));
        $this->assertStringContainsString('belongs to the realm "other"', $this->logger->all());
    }

    public function testTheRealmIsComparedWithoutCase(): void
    {
        $this->config->values["realm"] = "dev";
        $answer = str_replace('"username":"erin"', '"username":"erin","user-realm":"DEV"', self::PASSKEY_ACCEPT);
        $this->answerTheButtonWithPasskey("erin", $answer);

        $this->assertTrue($this->verify("erin", ""));
    }

    // ---- An old challenge and a failed request

    public function testAFailedRequestForTheChallengeWaitsAMinute(): void
    {
        $this->turnOnThePasskeyButton();
        $this->client->queue($this->response(403, '{"result":{"status":false,"error":{"code":303,"message":"No relying party"}}}'));

        for ($i = 0; $i < 3; $i++)
        {
            $this->provider->getTemplate(new FakeUser("erin")); // the push page loads again and again
        }
        $this->assertCount(1, $this->client->requests);

        $this->session->set("pi_passkeyFailedAt", time() - 61);
        $this->client->queue($this->response(200, self::INITIALIZE));
        $template = $this->provider->getTemplate(new FakeUser("erin"));

        $this->assertCount(2, $this->client->requests);
        $this->assertSame("INIT", json_decode($template->vars["passkeyChallenge"], true)["challenge"]);
        $this->assertNull($this->session->get("pi_passkeyFailedAt"));
    }

    public function testAnOldTriggeredChallengeIsReplacedByTheChallengeOfTheButton(): void
    {
        $this->turnOnThePasskeyButton();
        $this->templateAfterTrigger("erin", self::PASSKEY_CHALLENGE);
        $this->assertSame("passkey", $this->session->get("pi_mode"));

        // privacyIDEA drops the triggered challenge after 120 seconds by default.
        $this->session->set("pi_passkeyTriggeredAt", time() - 61);
        $this->client->queue($this->response(200, self::INITIALIZE));
        $template = $this->provider->getTemplate(new FakeUser("erin"));

        $this->assertSame("http://pi.test/validate/initialize", $this->client->requests[2]["uri"]);
        $this->assertSame("INIT", json_decode($template->vars["passkeyChallenge"], true)["challenge"]);
        $this->assertSame("777", $this->session->get("pi_passkeyTransactionId"));
        $this->assertSame("555", $this->session->get("pi_transactionId"), "The transaction of the other challenges stays.");
        $this->assertSame("otp", $template->vars["mode"], "The new challenge waits for the button.");
        $this->assertNull($this->session->get("pi_passkeyTriggeredAt"));
    }

    public function testWithTheButtonOffAnOldTriggeredChallengeStays(): void
    {
        $this->templateAfterTrigger("erin", self::PASSKEY_CHALLENGE); // setUp() turned the button off
        $this->session->set("pi_passkeyTriggeredAt", time() - 61);

        $template = $this->provider->getTemplate(new FakeUser("erin"));

        $this->assertCount(2, $this->client->requests, "No request for a new challenge.");
        $this->assertSame("CH", json_decode($template->vars["passkeyChallenge"], true)["challenge"]);
    }

    /**
     * @dataProvider valuesThatLeaveThePasskeyButtonOn
     */
    public function testThePasskeyButtonFollowsTheDefaultOfTheSetting($value): void
    {
        // The settings page shows a checked box for every value except "0". The provider must do the same.
        $this->turnOnThePasskeyButton();
        if ($value !== null)
        {
            $this->config->values["passkeybutton"] = $value;
        }
        $this->client->queue($this->response(200, self::INITIALIZE));

        $this->provider->getTemplate(new FakeUser("erin"));

        $this->assertCount(1, $this->client->requests);
    }

    public function valuesThatLeaveThePasskeyButtonOn(): array
    {
        return ["no value" => [null], "an empty value" => [""], "the value 1" => ["1"]];
    }

    // ---- Push and TiQR: the end of the wait

    private const POLL_PENDING = '{"detail":{"challenge_status":"pending"},"result":{"status":true,"value":false}}';
    private const POLL_DECLINED = '{"detail":{"challenge_status":"declined"},"result":{"status":true,"value":false}}';

    /** The push page sends its form while it waits for the confirmation. */
    private function pushPageLoads(): void
    {
        $this->session->set("pi_uid", "bob");
        $this->session->set("pi_transactionId", "1234");
        $this->session->set("pi_message", "Please confirm the authentication on your mobile device!");
        $this->session->set("pi_challengeTime", time());
        $this->request->params["mode"] = "push";
    }

    public function testADeclinedPushStopsTheWait(): void
    {
        $this->pushPageLoads();
        $this->client->queue($this->response(200, self::POLL_DECLINED));

        $this->assertSame("The login was declined on the phone.", $this->failureOf("bob", ""));
        $this->assertSame("otp", $this->session->get("pi_mode"), "The page stops the poll.");
        $template = $this->provider->getTemplate(new FakeUser("bob"));
        $this->assertSame("New challenge", $template->vars["newChallenge"]);
    }

    public function testAnUnansweredPushStopsAfterTheValidityTime(): void
    {
        // privacyIDEA answers "pending" also for an expired challenge.
        $this->pushPageLoads();
        $this->session->set("pi_challengeTime", time() - 121);
        $this->client->queue($this->response(200, self::POLL_PENDING));

        $this->assertSame("The login was not confirmed in time.", $this->failureOf("bob", ""));
        $this->assertSame("otp", $this->session->get("pi_mode"));
        $this->assertTrue($this->session->get("pi_offerNewChallenge"));
    }

    public function testAYoungPushKeepsWaiting(): void
    {
        $this->pushPageLoads();
        $this->client->queue($this->response(200, self::POLL_PENDING));

        $this->assertSame("Please confirm the authentication on your mobile device!", $this->failureOf("bob", ""));
        $this->assertSame("push", $this->session->get("pi_mode"));
        $this->assertNull($this->session->get("pi_offerNewChallenge"));
    }

    public function testAnErrorWhileThePushPageWaitsIsShownAndStopsThePoll(): void
    {
        $this->pushPageLoads();
        $this->client->queue($this->response(500, '{"result":{"status":false,"error":{"code":-500,"message":"database is gone"}}}'));

        $this->assertSame("database is gone", $this->failureOf("bob", ""), "Not the text of the push.");
        $this->assertSame("otp", $this->session->get("pi_mode"));
        $this->assertStringContainsString("error: [processPIResponse]", $this->logger->all());
    }

    // ---- The button "New challenge"

    public function testANewChallengeForgetsTheOldOneAndTriggersAgain(): void
    {
        $this->config->values["triggerchallenges"] = "1";
        $this->client->queue($this->response(200, self::AUTH_OK))->queue($this->response(200, self::CHALLENGE));
        $this->provider->getTemplate(new FakeUser("bob"));
        $this->assertSame("1234", $this->session->get("pi_transactionId"));

        $this->request->params["newChallenge"] = "1";
        $this->assertSame("New challenges are on the way.", $this->failureOf("bob", "000000"));
        $this->assertNull($this->session->get("pi_transactionId"));
        $this->assertCount(2, $this->client->requests, "The click alone sends nothing.");

        $this->request->params = [];
        $this->client->queue($this->response(200, self::AUTH_OK))->queue($this->response(200, self::CHALLENGE));
        $this->provider->getTemplate(new FakeUser("bob"));
        $this->assertSame("http://pi.test/validate/triggerchallenge", $this->client->requests[3]["uri"], "The next page triggers again.");
    }

    public function testARefusedAnswerToAChallengeOffersANewChallenge(): void
    {
        $this->session->set("pi_uid", "bob");
        $this->session->set("pi_transactionId", "1234");
        $this->client->queue($this->response(200, '{"detail":{"message":"Response did not match the challenge."},"result":{"status":true,"value":false}}'));

        $this->failureOf("bob", "287082");

        $this->assertTrue($this->session->get("pi_offerNewChallenge"));
    }

    public function testARefusedAnswerWithoutChallengeOffersNothing(): void
    {
        $this->client->queue($this->response(200, self::WRONG_OTP));

        $this->failureOf("alice", "000000");

        $this->assertNull($this->session->get("pi_offerNewChallenge"));
    }

    // ---- The challenges of an answer

    /** An answer of /validate/check with these multi_challenge entries and these extra fields of detail. */
    private function challengeAnswer(array $entries, array $detail = []): string
    {
        return json_encode(["detail" => array_merge(["message" => "challenge", "transaction_id" => "2222",
            "multi_challenge" => $entries], $detail), "result" => ["status" => true, "value" => false]]);
    }

    public function testANewChallengeRemovesTheButtonsOfTheOldOne(): void
    {
        $this->config->values["triggerchallenges"] = "1";
        $this->client->queue($this->response(200, self::AUTH_OK))->queue($this->response(200, $this->challengeAnswer(
            [["client_mode" => "poll", "type" => "push"], ["client_mode" => "interactive", "type" => "hotp"]])));
        $this->provider->getTemplate(new FakeUser("bob"));
        $this->assertSame("1", $this->session->get("pi_pushAvailable"));

        // The HOTP answer starts a PIN change with a new transaction.
        $this->client->queue($this->response(200, $this->challengeAnswer(
            [["client_mode" => "interactive", "type" => "hotp", "attributes" => null]])));
        $this->failureOf("bob", "287082");

        $this->assertNull($this->session->get("pi_pushAvailable"), "No push button of the old challenge.");
    }

    public function testTheSignRequestsOfSeveralWebAuthnTokensAreJoined(): void
    {
        $sign = function (string $id)
        {
            return ["client_mode" => "webauthn", "type" => "webauthn", "attributes" => ["webAuthnSignRequest" =>
                ["challenge" => "SAME", "rpId" => "localhost", "allowCredentials" => [["id" => $id, "type" => "public-key"]]]]];
        };
        $this->client->queue($this->response(200, $this->challengeAnswer([$sign("KEY1"), $sign("KEY2"), $sign("KEY1")])));

        $this->failureOf("erin", "1234");

        $request = $this->session->get("pi_webAuthnSignRequest");
        $this->assertSame("SAME", $request->challenge);
        $this->assertSame(["KEY1", "KEY2"], array_map(function ($c) { return $c->id; }, $request->allowCredentials));
    }

    public function testAUserWithOnlyTiqrGetsTheTiqrPage(): void
    {
        // privacyIDEA names the mode of a TiQR token "poll", like a push token.
        $this->client->queue($this->response(200, $this->challengeAnswer(
            [["client_mode" => "poll", "type" => "tiqr", "attributes" => ["img" => "TIQRQR"]]], ["preferred_client_mode" => "poll"])));

        $this->failureOf("tina", "1234");

        $this->assertSame("tiqr", $this->session->get("pi_mode"));
        $this->assertSame("TIQRQR", $this->session->get("pi_tiqrImage"));
    }

    public function testPollWithAPushTokenStaysPush(): void
    {
        $this->client->queue($this->response(200, $this->challengeAnswer(
            [["client_mode" => "poll", "type" => "tiqr", "attributes" => ["img" => "Q"]], ["client_mode" => "poll", "type" => "push"]],
            ["preferred_client_mode" => "poll"])));

        $this->failureOf("bob", "1234");

        $this->assertSame("push", $this->session->get("pi_mode"));
    }

    public function testThePushEnrollmentShowsItsQrCodeAndPolls(): void
    {
        // An enrollment answer has no preferred_client_mode. The page must poll and show the QR code in push mode.
        $this->client->queue($this->response(200, $this->challengeAnswer(
            [["client_mode" => "poll", "type" => "push", "image" => "PUSHQR", "link" => "otpauth://pipush/PIPU1?url=x"]],
            ["enroll_via_multichallenge" => true])));

        $template = null;
        $this->failureOf("bob", "1234");
        $template = $this->provider->getTemplate(new FakeUser("bob"));

        $this->assertSame("push", $template->vars["mode"]);
        $this->assertSame("PUSHQR", $template->vars["enrollImage"]);
        $this->assertSame("otpauth://pipush/PIPU1?url=x", $template->vars["enrollLink"]);
        $this->assertArrayNotHasKey("imgPush", $template->vars);
    }

    public function testOnlyAnOtpauthLinkIsShown(): void
    {
        $this->client->queue($this->response(200, $this->challengeAnswer(
            [["client_mode" => "interactive", "type" => "hotp", "image" => "QR", "link" => "javascript:alert(1)"]],
            ["enroll_via_multichallenge" => true])));

        $this->failureOf("bob", "1234");

        $this->assertNull($this->session->get("pi_enrollLink"));
        $this->assertSame("QR", $this->session->get("pi_enrollImage"));
    }

    public function testAnEnrollmentAfterAPushLoginIsShown(): void
    {
        // The final answer in push mode can chain the enrollment of a new token. Before, push mode did not parse it.
        $this->pushPageLoads();
        $this->client->queue($this->response(200, '{"detail":{"challenge_status":"accept"},"result":{"status":true,"value":true}}'))
            ->queue($this->response(200, $this->challengeAnswer(
                [["client_mode" => "interactive", "type" => "totp", "image" => "TOTPQR"]], ["enroll_via_multichallenge" => true])));

        $this->failureOf("bob", "");

        $this->assertSame("2222", $this->session->get("pi_transactionId"), "The OTP of the new token answers the new transaction.");
        $this->assertSame("TOTPQR", $this->session->get("pi_enrollImage"));
    }

    public function testASmartphoneEnrollmentIsNotOffered(): void
    {
        $this->client->queue($this->response(200, $this->challengeAnswer(
            [["client_mode" => "poll", "type" => "smartphone", "image" => "CONTAINERQR"]], ["enroll_via_multichallenge" => true])));

        $this->failureOf("bob", "1234");

        $this->assertNull($this->session->get("pi_enrollImage"));
        $this->assertSame("otp", $this->session->get("pi_mode"));
    }

    // ---- Enrollment at the login: passkey registration, cancel, the address of email and SMS

    /** The answer that asks for the registration of a passkey (passkey.md test_09, without the offline policy). */
    private function passkeyRegistrationAnswer(array $detail = []): string
    {
        return $this->challengeAnswer([[
            "serial" => "PIPK7", "rollout_state" => "clientwait", "transaction_id" => "2222",
            "passkey_registration" => [
                "rp" => ["id" => "localhost", "name" => "ownCloud"],
                "user" => ["id" => "VVNFUklE", "name" => "alice", "displayName" => "alice"],
                "challenge" => "REGCHALLENGE",
                "pubKeyCredParams" => [["alg" => -7, "type" => "public-key"]],
                "timeout" => 12000, "excludeCredentials" => [],
                "authenticatorSelection" => ["residentKey" => "required", "userVerification" => "preferred"],
                "attestation" => "none"]]],
            array_merge(["enroll_via_multichallenge" => true, "enroll_via_multichallenge_optional" => false,
                "message" => "Please confirm the registration with your passkey!"], $detail));
    }

    private const REGISTRATION_SIGNED = '{"credential_id":"CRED","rawId":"CRED","authenticatorAttachment":"platform","attestationObject":"ATT","clientDataJSON":"CDJ"}';

    /** The browser answers the registration. */
    private function answerTheRegistration(string $response = self::REGISTRATION_SIGNED): void
    {
        $this->request->params = ["mode" => "passkeyRegistration", "passkeyRegistrationResponse" => $response,
            "origin" => "http://localhost:8080"];
    }

    public function testAPasskeyRegistrationStartsTheRegistrationMode(): void
    {
        $this->client->queue($this->response(200, $this->passkeyRegistrationAnswer()));

        $this->assertSame("Please confirm the registration with your passkey!", $this->failureOf("alice", "anything"));
        $template = $this->provider->getTemplate(new FakeUser("alice"));

        $this->assertSame("passkeyRegistration", $template->vars["mode"]);
        $this->assertSame("REGCHALLENGE", json_decode($template->vars["passkeyRegistration"], true)["challenge"]);
        $this->assertSame("1", $template->vars["passkeyRegistrationAutoStart"]);
        $this->assertSame("", $template->vars["autoSubmitOtpLength"]);
        $this->assertArrayNotHasKey("cancelEnrollment", $template->vars, "The enrollment is not optional.");
    }

    public function testThePasskeyRegistrationSendsTheFieldsOfPrivacyIdea(): void
    {
        $this->config->values["realm"] = "dev";
        $this->client->queue($this->response(200, $this->passkeyRegistrationAnswer()))
            ->queue($this->response(200, '{"detail":{"message":"Found matching challenge","serial":"PIPK7","username":"alice"},"result":{"authentication":"ACCEPT","status":true,"value":true}}'));
        $this->failureOf("alice", "anything");
        $this->answerTheRegistration();

        $this->assertTrue($this->verify("alice", ""));

        $request = $this->client->requests[1];
        $this->assertSame([
            "user" => "alice", "transaction_id" => "2222", "serial" => "PIPK7", "type" => "passkey",
            "credential_id" => "CRED", "rawId" => "CRED", "attestationObject" => "ATT", "clientDataJSON" => "CDJ",
            "authenticatorAttachment" => "platform", "realm" => "dev"], $request["options"]["form_params"]);
        $this->assertSame("http://localhost:8080", $request["options"]["headers"]["Origin"]);
        $this->assertSame([], $this->session->data, "The login is done.");
    }

    public function testARefusedRegistrationDoesNotStartAgainByItself(): void
    {
        $this->client->queue($this->response(200, $this->passkeyRegistrationAnswer()))
            ->queue($this->response(400, '{"result":{"status":false,"error":{"code":-1,"message":"The registration is not valid."}}}'));
        $this->failureOf("alice", "anything");
        $this->answerTheRegistration();

        $this->assertSame("The registration is not valid.", $this->failureOf("alice", ""));
        $template = $this->provider->getTemplate(new FakeUser("alice"));

        $this->assertSame("0", $template->vars["passkeyRegistrationAutoStart"], "The button tries again.");
        $this->assertSame("Register passkey", $template->vars["passkeyRegistrationLabel"]);
    }

    public function testARegistrationFromAnotherOriginIsRefused(): void
    {
        $this->client->queue($this->response(200, $this->passkeyRegistrationAnswer()));
        $this->failureOf("alice", "anything");
        $this->answerTheRegistration();
        $this->request->params["origin"] = "https://evil.example.com";

        $this->assertSame("Failed to authenticate.", $this->failureOf("alice", ""));
        $this->assertCount(1, $this->client->requests);
    }

    public function testAnIncompleteRegistrationSendsNothing(): void
    {
        $this->client->queue($this->response(200, $this->passkeyRegistrationAnswer()));
        $this->failureOf("alice", "anything");
        $this->answerTheRegistration('{"credential_id":"CRED","attestationObject":"ATT","clientDataJSON":"CDJ"}');

        $this->assertSame("Failed to authenticate.", $this->failureOf("alice", ""));
        $this->assertCount(1, $this->client->requests, "No rawId, no request.");
    }

    public function testARegistrationWithoutASerialSendsNothing(): void
    {
        $answer = json_decode($this->passkeyRegistrationAnswer(), true);
        unset($answer["detail"]["multi_challenge"][0]["serial"]);
        $this->client->queue($this->response(200, json_encode($answer)));
        $this->failureOf("alice", "anything");
        $this->answerTheRegistration();

        $this->assertSame("Failed to authenticate.", $this->failureOf("alice", ""));
        $this->assertCount(1, $this->client->requests, "No serial, no request.");
    }

    public function testAnOptionalEnrollmentCanBeCancelled(): void
    {
        $this->client->queue($this->response(200, $this->challengeAnswer(
            [["client_mode" => "interactive", "type" => "hotp", "image" => "QR"]],
            ["enroll_via_multichallenge" => true, "enroll_via_multichallenge_optional" => true])))
            ->queue($this->response(200, '{"detail":{"message":"Cancelled enrollment via multichallenge"},"result":{"authentication":"ACCEPT","status":true,"value":true}}'));
        $this->failureOf("alice", "anything");
        $this->assertSame("Cancel enrollment", $this->provider->getTemplate(new FakeUser("alice"))->vars["cancelEnrollment"]);

        $this->request->params = ["cancelEnrollment" => "1", "mode" => "otp"];
        $this->assertTrue($this->verify("alice", ""));

        // No user name: with it, privacyIDEA starts a new enrollment after the cancel (post policy
        // multichallenge_enroll_via_validate).
        $this->assertSame(["transaction_id" => "2222", "cancel_enrollment" => "true"],
            $this->client->requests[1]["options"]["form_params"]);
    }

    public function testAMandatoryEnrollmentCannotBeCancelled(): void
    {
        $this->client->queue($this->response(200, $this->challengeAnswer(
            [["client_mode" => "interactive", "type" => "hotp", "image" => "QR"]], ["enroll_via_multichallenge" => true])));
        $this->failureOf("alice", "anything");

        $this->request->params = ["cancelEnrollment" => "1", "mode" => "otp"];
        $this->assertSame("Failed to authenticate.", $this->failureOf("alice", ""));
        $this->assertCount(1, $this->client->requests, "The app does not send the cancel.");
    }

    public function testAnOptionalPasskeyRegistrationCanBeCancelledToo(): void
    {
        $this->client->queue($this->response(200, $this->passkeyRegistrationAnswer(["enroll_via_multichallenge_optional" => true])))
            ->queue($this->response(200, '{"detail":{"message":"Cancelled enrollment via multichallenge"},"result":{"authentication":"ACCEPT","status":true,"value":true}}'));
        $this->failureOf("alice", "anything");
        $this->assertSame("Cancel enrollment", $this->provider->getTemplate(new FakeUser("alice"))->vars["cancelEnrollment"]);

        $this->request->params = ["cancelEnrollment" => "1", "mode" => "passkeyRegistration"];
        $this->assertTrue($this->verify("alice", ""));
        $this->assertSame("true", $this->client->requests[1]["options"]["form_params"]["cancel_enrollment"]);
    }

    public function testTheAddressOfAnEmailEnrollmentIsVisible(): void
    {
        // multichallenge-enroll.md test_03: the first step asks for the address. The next step asks for the OTP.
        $this->client->queue($this->response(200, $this->challengeAnswer(
            [["client_mode" => "interactive", "type" => "email", "image" => null]], ["enroll_via_multichallenge" => true])));
        $this->failureOf("alice", "anything");
        $this->assertTrue($this->provider->getTemplate(new FakeUser("alice"))->vars["plainInput"]);

        $this->client->queue($this->response(200, $this->challengeAnswer(
            [["client_mode" => "interactive", "type" => "email"]], ["preferred_client_mode" => "interactive"])));
        $this->failureOf("alice", "alice@example.com");
        $this->assertArrayNotHasKey("plainInput", $this->provider->getTemplate(new FakeUser("alice"))->vars);
    }

    public function testAnEnrollmentPageHasNoPasskeyButton(): void
    {
        // The first page has the button. After the first step, privacyIDEA asks for the enrollment of a token.
        $this->turnOnThePasskeyButton();
        $this->client->queue($this->response(200, '{"detail":{"passkey":{"challenge":"INIT","rpId":"localhost","transaction_id":"777"},"transaction_id":"777"},"result":{"authentication":"CHALLENGE","status":true,"value":false}}'));
        $this->assertArrayHasKey("passkeyChallenge", $this->provider->getTemplate(new FakeUser("alice"))->vars);

        $this->client->queue($this->response(200, $this->challengeAnswer(
            [["client_mode" => "interactive", "type" => "totp", "image" => "QR"]], ["enroll_via_multichallenge" => true])));
        $this->failureOf("alice", "anything");

        $this->assertArrayNotHasKey("passkeyChallenge", $this->provider->getTemplate(new FakeUser("alice"))->vars);
        $this->assertCount(2, $this->client->requests, "The enrollment page does not ask for a new passkey challenge.");
    }

    public function testTheOtpOfAnOtpEnrollmentStaysHidden(): void
    {
        // Only the address of an email or SMS enrollment shows in clear text. The OTP of a new HOTP token does not.
        $this->client->queue($this->response(200, $this->challengeAnswer(
            [["client_mode" => "interactive", "type" => "hotp", "image" => "QR"]], ["enroll_via_multichallenge" => true])));
        $this->failureOf("alice", "anything");
        $this->assertArrayNotHasKey("plainInput", $this->provider->getTemplate(new FakeUser("alice"))->vars);
    }

    public function testAPushEnrollmentWaitsLongerThanALogin(): void
    {
        $this->client->queue($this->response(200, $this->challengeAnswer(
            [["client_mode" => "poll", "type" => "push", "image" => "PUSHQR"]], ["enroll_via_multichallenge" => true])));
        $this->failureOf("bob", "anything");

        // The user scans the QR code and sets up the token on the phone. This takes longer than a login.
        $this->request->params = ["mode" => "push"];
        $this->session->set("pi_challengeTime", time() - 121);
        $this->client->queue($this->response(200, self::POLL_PENDING));
        $this->failureOf("bob", "");
        $this->assertSame("push", $this->session->get("pi_mode"), "Still waiting.");

        $this->session->set("pi_challengeTime", time() - 601);
        $this->client->queue($this->response(200, self::POLL_PENDING));
        $this->assertSame("The login was not confirmed in time.", $this->failureOf("bob", ""));
    }

    // ---- Auto submit

    /**
     * @dataProvider challengesAndAutoSubmit
     */
    public function testAutoSubmitIsOnlyForAnOtp(array $entries, array $detail, string $expected): void
    {
        $this->config->values["autoSubmitOtpLength"] = "6";
        $this->client->queue($this->response(200, $this->challengeAnswer($entries, $detail)));
        $this->failureOf("bob", "1234");

        $template = $this->provider->getTemplate(new FakeUser("bob"));

        $this->assertSame($expected, $template->vars["autoSubmitOtpLength"]);
    }

    public function challengesAndAutoSubmit(): array
    {
        return [
            "a HOTP challenge" => [[["client_mode" => "interactive", "type" => "hotp"]], [], "6"],
            "an email challenge" => [[["client_mode" => "interactive", "type" => "email"]], [], "6"],
            "a question" => [[["client_mode" => "interactive", "type" => "question"]], [], ""],
            "the enrollment of an SMS token (phone number)" => [[["client_mode" => "interactive", "type" => "sms"]],
                ["enroll_via_multichallenge" => true], ""],
        ];
    }

    public function testAutoSubmitIsOffForTheNewPin(): void
    {
        $this->config->values["autoSubmitOtpLength"] = "6";
        // The OTP challenge before a PIN change has pin_change. Its own OTP can still be sent by itself.
        $this->client->queue($this->response(200, $this->challengeAnswer([["client_mode" => "interactive", "type" => "hotp"]],
            ["pin_change" => true])));
        $this->failureOf("bob", "1234");
        $this->assertSame("6", $this->provider->getTemplate(new FakeUser("bob"))->vars["autoSubmitOtpLength"]);

        // The next challenge asks for the new PIN.
        $this->client->queue($this->response(200, $this->challengeAnswer([["client_mode" => "interactive", "type" => "hotp"]])));
        $this->failureOf("bob", "287082");
        $this->assertSame("", $this->provider->getTemplate(new FakeUser("bob"))->vars["autoSubmitOtpLength"]);
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

    public function testTheLogHasNoSeedOfAnEnrolledToken(): void
    {
        // enroll_via_multichallenge: the answer has the otpauth link with the seed and the QR code of the new token.
        $enroll = '{"detail":{"client_mode":"interactive","enroll_via_multichallenge":true,'
            . '"image":"data:image/png;base64,QRDATA","link":"otpauth://hotp/OATH1?secret=SEEDSEEDSEED&counter=1",'
            . '"message":"Please scan the QR code and enter the OTP value!","multi_challenge":[{"client_mode":"interactive",'
            . '"image":"data:image/png;base64,QRDATA","link":"otpauth://hotp/OATH1?secret=SEEDSEEDSEED&counter=1",'
            . '"message":"Please scan the QR code and enter the OTP value!","serial":"OATH1","transaction_id":"999","type":"hotp"}],'
            . '"serial":"OATH1","transaction_id":"999","type":"hotp"},'
            . '"result":{"authentication":"CHALLENGE","status":true,"value":false}}';
        $this->client->queue($this->response(200, $enroll));

        $this->failureOf("alice", "123456");

        $log = $this->logger->all();
        $this->assertStringNotContainsString("SEEDSEEDSEED", $log);
        $this->assertStringNotContainsString("QRDATA", $log);
        $this->assertStringContainsString('"transaction_id":"999"', $log, "The log still tells what happened.");
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
