/**
 * @author Cornelius Kölbel <cornelius.koelbel@netknights.it>
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

window.onload = function ()
{
    setInterval(function ()
    {
        $.ajax({
            url: location.href,
            type: 'post',
            success: window.onload = function (result)
            {
                if (result.search("<input type=\"hidden\" id=\"ResponseStatus\" value=\"false\">") === -1)
                {
                    location.reload();
                }
            }
        });
    }, 1000);
};