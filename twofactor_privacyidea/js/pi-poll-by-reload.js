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

/**
 * True if the browser asks privacyIDEA itself (pi-poll-in-browser.js). The conditions are the same as there.
 */
function piPollInBrowserActive()
{
    return piGetValue("pollInBrowser") === "1"
        && piGetValue("pollInBrowserUrl").length > 0
        && piGetValue("transactionId").length > 0
        && String(piGetValue("pollInBrowserFailed")) !== "true"
        && typeof (Worker) !== "undefined";
}

function piPollByReload()
{
    let mode = document.getElementById("mode").value;
    if (mode === "push" || mode === "tiqr")
    {
        const pollingIntervals = [4, 3, 2];
        let loadCounter = document.getElementById("loadCounter").value;
        let refreshTime;

        if (loadCounter > (pollingIntervals.length - 1))
        {
            refreshTime = pollingIntervals[(pollingIntervals.length - 1)];
        }
        else
        {
            refreshTime = pollingIntervals[Number(loadCounter - 1)];
        }
        // While the browser asks privacyIDEA every second, the page reloads only every 30 seconds.
        // The server then still notices an expired challenge. If the browser poll fails, it calls this function again.
        if (piPollInBrowserActive())
        {
            refreshTime = 30;
        }

        refreshTime *= 1000;

        window.setTimeout(function ()
        {
            document.forms["piLoginForm"].submit();
        }, refreshTime);
    }
}

// Wait until the document is ready
document.addEventListener("DOMContentLoaded", function ()
{
    piPollByReload();
});