/**
 * @author Lukas Matusiewicz <lukas.matusiewicz@netknights.it>
 *
 * @license AGPL-3.0
 *
 * This code is a free software: You can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 */

// The worker file lies next to this script. Take its address from the address of this script, so that it
// works in every app directory (apps, custom, apps-external). document.currentScript is only set while
// the script runs for the first time, so read it here.
const piPollScriptUrl = document.currentScript ? document.currentScript.src : null;

function piPollInBrowserFailed(message)
{
    console.log("Poll in browser error: " + message);
    piSetValue("errorMessage", "Poll in browser error: " + message);
    piSetValue("pollInBrowserFailed", true);
    piEnableElement("pushButton");
}

function piPollInBrowser()
{
    // The hidden input holds "0" or "1". Compare the value. The string "0" counts as true in JavaScript.
    if (piGetValue("pollInBrowser") !== "1"
        || piGetValue("pollInBrowserUrl").length === 0
        || piGetValue("transactionId").length === 0)
    {
        return;
    }

    if (typeof (Worker) === "undefined" || piPollScriptUrl === null)
    {
        piPollInBrowserFailed("The browser does not support the Web Worker.");
        return;
    }

    piDisableElement("pushButton");
    const worker = new Worker(new URL("pi-poll-transaction.worker.js", piPollScriptUrl).href);

    // Stop polling when the form is sent, for example when the user enters an OTP.
    document.forms["piLoginForm"].addEventListener("submit", function ()
    {
        worker.terminate();
    });

    worker.addEventListener("message", function (e)
    {
        const data = e.data;
        switch (data.status)
        {
            case "success":
                document.forms["piLoginForm"].submit();
                break;
            case "error":
                worker.terminate();
                piPollInBrowserFailed(data.message);
                break;
        }
    });
    worker.addEventListener("error", function (e)
    {
        worker.terminate();
        piPollInBrowserFailed(e.message || "The worker could not be loaded.");
    });

    worker.postMessage({'cmd': 'url', 'msg': piGetValue("pollInBrowserUrl")});
    worker.postMessage({'cmd': 'transactionId', 'msg': piGetValue("transactionId")});
    worker.postMessage({'cmd': 'start'});
}

// Wait until the document is ready
document.addEventListener("DOMContentLoaded", function ()
{
    piPollInBrowser();
});
