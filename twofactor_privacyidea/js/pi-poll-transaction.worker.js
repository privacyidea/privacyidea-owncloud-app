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

// This file runs as a web worker. Do not load it as a normal script.
// It asks privacyIDEA every second if the push message is confirmed.
// Messages to the worker: {cmd: 'url', msg: <base URL of privacyIDEA>}, {cmd: 'transactionId', msg: <id>}, {cmd: 'start'}.
// Messages from the worker: {status: 'success' | 'error', message: <text>}. The worker closes itself after both.

const POLL_INTERVAL_MS = 1000;

let pollUrl = "";
let transactionId = "";

self.addEventListener('message', function (e)
{
    const data = e.data;

    switch (data.cmd)
    {
        case 'url':
            pollUrl = String(data.msg).replace(/\/+$/, "") + "/validate/polltransaction";
            break;
        case 'transactionId':
            transactionId = String(data.msg);
            break;
        case 'start':
            if (pollUrl.length > 0 && transactionId.length > 0)
            {
                pollTransactionInBrowser();
            }
            break;
    }
});

async function pollTransactionInBrowser()
{
    try
    {
        const response = await fetch(pollUrl + "?transaction_id=" + encodeURIComponent(transactionId),
            {headers: {'Accept': 'application/json'}});
        if (!response.ok)
        {
            throw new Error(response.statusText || ("HTTP status " + response.status));
        }
        const json = await response.json();
        if (json && json.result && json.result.value === true)
        {
            self.postMessage({'message': 'Polling in browser: Push message confirmed!', 'status': 'success'});
            self.close();
            return;
        }
    }
    catch (error)
    {
        self.postMessage({'message': String(error && error.message ? error.message : error), 'status': 'error'});
        self.close();
        return;
    }
    // Not confirmed yet. Ask again after one interval. The next request starts after this one ended.
    setTimeout(pollTransactionInBrowser, POLL_INTERVAL_MS);
}
