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

window.onload = () =>
{
    if (piGetValue("pollInBrowser")
        && piGetValue("pollInBrowserUrl").length > 0
        && piGetValue("transactionId").length > 0)
    {
        piDisableElement("pushButton");
        let worker;
        if (typeof (Worker) !== "undefined")
        {
            if (typeof (worker) == "undefined")
            {
                worker = new Worker("/apps-external/twofactor_privacyidea/js/pi-poll-transaction.worker.js");
                document.getElementById("submitButton").addEventListener('click', function (e)
                {
                    worker.terminate();
                    worker = undefined;
                });
                worker.postMessage({'cmd': 'url', 'msg': piGetValue("pollInBrowserUrl")});
                worker.postMessage({'cmd': 'transactionId', 'msg': piGetValue("transactionId")});
                worker.postMessage({'cmd': 'start'});
                worker.addEventListener('message', function (e)
                {
                    let data = e.data;
                    switch (data.status)
                    {
                        case 'success':
                            document.forms["piLoginForm"].submit();
                            break;
                        case 'error':
                            console.log("Poll in browser error: " + data.message);
                            piSetValue("errorMessage", "Poll in browser error: " + data.message);
                            piSetValue("pollInBrowserFailed", true);
                            piEnableElement("pushButton");
                            worker = undefined;
                    }
                });
            }
        }
        else
        {
            console.log("Sorry! No Web Worker support.");
            worker.terminate();
            piSetValue("errorMessage", "Poll in browser error: The browser doesn't support the Web Worker.");
            piSetValue("pollInBrowserFailed", true);
            piEnableElement("pushButton");
        }
    }
}