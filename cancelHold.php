<?php 
    //Serialize and validate input data
    $patronId = filter_var($_POST['patronId'], FILTER_VALIDATE_INT);
    $holdId = filter_var($_POST['holdId'], FILTER_VALIDATE_INT);
    
    //Build table to display hold information, mirroring notice email
    $holdDisplay = "    <table class='hold bib'>
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
        <title>Cancel Hold</title>
        <script
            src="https://code.jquery.com/jquery-3.4.1.min.js"
            integrity="sha256-CSXorXvZcTkaix6Yvo6HppcZGetbYMGWSFlBw8HfCJo="
            crossorigin="anonymous"></script>
        <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/jquery-confirm/3.3.2/jquery-confirm.min.css">
        <script src="//cdnjs.cloudflare.com/ajax/libs/jquery-confirm/3.3.2/jquery-confirm.min.js"></script>
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css" integrity="sha384-50oBUHEmvpQ+1lW4y57PTFmhCaXp0ML5d60M1M7uH2+nqUivzIebhndOJK28anvf" crossorigin="anonymous">
    </head>
    <body>
        <script>
            $( document ).ready(function() {
                $.confirm({
                    icon: 'fa fa-warning',
                    title: 'Are you sure you want to cancel this hold?',
                    content: "<?php echo preg_replace("/\r|\n/", "", $holdDisplay); ?>",
                    type: 'red',
                    boxWidth: '30%',
                    useBootstrap: false,
                    buttons: {
                        yes: {
                            text: 'Yes',
                            btnClass: 'btn-red',
                            keys: ['enter'],
                            action: function(){
                                $.dialog({
                                    title: 'Processed',
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
                                            self.setContent('patronId: ' + response.patronId);
                                            self.setContentAppend('<br>holdId: ' + response.holdId);
                                            self.setTitle(response.name);
                                        }).fail(function(){
                                            self.setContent('Something went wrong.');
                                        });
                                    },
                                    boxWidth: '30%',
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
                                    content: 'Your hold has not been canceled, and must be picked up by <?php echo $_POST['expDate']; ?>',
                                    boxWidth: '30%',
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