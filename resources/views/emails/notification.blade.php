<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $subject ?? 'Notification' }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f4; font-family:Arial, Helvetica, sans-serif;">

    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f4f4; padding:20px 0;">
        <tr>
            <td align="center">

                <table width="600" cellpadding="0" cellspacing="0" border="0" style="background:#ffffff; border-radius:8px; padding:24px;">
                    <tr>
                        <td style="font-size:16px; color:#333333; line-height:1.6;">

                            {!! $body ?? '' !!}

                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>

</body>
</html>