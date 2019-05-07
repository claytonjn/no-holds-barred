<?php 
    $patronId = filter_var($_POST['patronId'], FILTER_VALIDATE_INT);
    $holdId = filter_var($_POST['holdId'], FILTER_VALIDATE_INT);
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
        <title>Are you sure?</title>
        <script
            src="https://code.jquery.com/jquery-3.4.1.slim.min.js"
            integrity="sha256-pasqAKBDmFT4eHoN2ndd6lN370kFiGUFyTiUHWhU7k8="
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
                                $.alert('Confirmed!');
                            }
                        },
                        no: {
                            text: 'No',
                            keys: ['esc'],
                            action: function () {
                                $.dialog({
                                    title: 'Not Canceled',
                                    content: 'Your hold has not been canceled.',
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