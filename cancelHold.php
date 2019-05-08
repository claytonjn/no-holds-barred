<?php 
    //Serialize and validate input data
    $patronId = filter_var($_POST['patronId'], FILTER_VALIDATE_INT);
    $holdId = filter_var($_POST['holdId'], FILTER_VALIDATE_INT);
    
    //Build table to display hold information, mirroring notice email
    $holdDisplay = "    <table style='margin-left:auto; margin-right:auto;'>
                            <tr>
                                <td class='cover' rowspan=5>{$_POST['coverImg']}</td>
                                <td class='title'>{$_POST['link']}</td>
                            </tr>
                            <tr>
                                <td class='edition_author'>{$_POST['bestAuthor']}</td>
                            </tr>
                            <tr>
                                <td class='bcode2'>{$_POST['matType']}</td>
                            </tr>
                            <tr>
                                <td class='pickuplocation'><b>Pickup At: </b>{$_POST['pickupLocation']}</td>
                            </tr>
                            <tr>
                                <td class='expdate'><b>Pickup By: </b>{$_POST['expDate']}</td>
                            </tr>
                        </table>";
?>

<!DOCTYPE html>
<html>
    <head>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Cancel Hold</title>
        <script
            src="https://code.jquery.com/jquery-3.4.1.min.js"
            integrity="sha256-CSXorXvZcTkaix6Yvo6HppcZGetbYMGWSFlBw8HfCJo="
            crossorigin="anonymous"></script>
        <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/jquery-confirm/3.3.2/jquery-confirm.min.css">
        <script src="//cdnjs.cloudflare.com/ajax/libs/jquery-confirm/3.3.2/jquery-confirm.min.js"></script>
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css" integrity="sha384-50oBUHEmvpQ+1lW4y57PTFmhCaXp0ML5d60M1M7uH2+nqUivzIebhndOJK28anvf" crossorigin="anonymous">
        <style>
            @media only screen and (max-width: 600px) {
                .jconfirm-box {
                    width:  90% !important;
                }
            }
        </style>
    </head>
    <body>
        <script>
            $( document ).ready(function() {
                $.confirm({
                    icon: 'fas fa-exclamation-triangle',
                    title: 'Are you sure you want to cancel this hold?',
                    content: "<?php echo preg_replace("/\r|\n/", "", $holdDisplay); ?>",
                    type: 'red',
                    boxWidth: '31%',
                    useBootstrap: false,
                    buttons: {
                        yes: {
                            text: 'Yes',
                            btnClass: 'btn-red',
                            keys: ['enter'],
                            action: function(){
                                $.dialog({
                                    title: false,
                                    content: function () {
                                        var self = this;
                                        return $.ajax({
                                            url: 'cancelHoldProcess.php',
                                            dataType: 'json',
                                            method: 'post',
                                            data:
                                                    {
                                                        "patronId": "<?php echo $patronId; ?>",
                                                        "holdId": "<?php echo $holdId; ?>"
                                                    }
                                        }).done(function (response) {
                                            self.setType(response.type)
                                            self.setTitle(response.title)
                                            self.setContent(response.content);
                                        }).fail(function(){
                                            var type = `orange`;
                                            var title = `There was a problem cancelling this hold`;
                                            var content = ` For assistance, please contact the Circulation department.<br>
                                                            <span style='display:inline-block; font-weight:bold; padding-left:2em; padding-right:1em;'><i class='fas fa-at'></i></span> <a href='mailto:email@domain.org'>email@domain.org</a><br>
                                                            <span style='display:inline-block; font-weight:bold; padding-left:2em; padding-right:1em;'><i class='fas fa-phone'></i></span> <a href='tel:1234567890'>(123) 456-7890</a>`;
                                            self.setType(type)
                                            self.setTitle(title);
                                            self.setContent(content);
                                        });
                                    },
                                    boxWidth: '31%',
                                    useBootstrap: false,
                                    closeIcon: false
                                });
                            }
                        },
                        no: {
                            text: 'No',
                            keys: ['esc'],
                            action: function () {
                                $.dialog({
                                    title: 'Not Canceled',
                                    content: 'Your hold has not been canceled, and must be picked up by <b><?php echo $_POST['expDate']; ?></b>',
                                    boxWidth: '31%',
                                    useBootstrap: false,
                                    closeIcon: false
                                });
                            }
                        }
                    }
                });
            });
        </script>
    </body>
</html>