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

let url;
let params;
self.addEventListener('message', function (e)
{
    let data = e.data;

    switch (data.cmd)
    {
        case 'url':
            url = data.msg + "/validate/polltransaction";
            break;
        case 'transactionId':
            params = "transaction_id=" + data.msg;
            break;
        case 'start':
            if (url.length > 0 && params.length > 0)
            {
                setInterval("pollTransactionInBrowser()", 300);
            }
            break;
    }
})

function pollTransactionInBrowser()
{
    const request = new XMLHttpRequest();

    request.open("GET", url + "?" + params, false);

    request.onload = (e) =>
    {
        try
        {
            if (request.readyState === 4)
            {
                if (request.status === 200)
                {
                    const response = JSON.parse(request.response);
                    if (response['result']['value'] === true)
                    {
                        self.postMessage({'message': 'Polling in browser: Push message confirmed!', 'status': 'success'});
                        self.close();
                    }
                }
                else
                {
                    self.postMessage({'message': request.statusText, 'status': 'error'});
                    self.close();
                }
            }
        }
        catch (e)
        {
            self.postMessage({'message': e, 'status': 'error'});
            self.close();
        }
    };

    request.onerror = (e) =>
    {
        self.postMessage({'message': request.statusText, 'status': 'error'});
        self.close();
    };

    request.send();
}