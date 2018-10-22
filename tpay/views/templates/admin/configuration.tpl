<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
<script type="text/javascript">

    jQuery(document).ready(function () {
        getValuesTpay();

        jQuery('#TPAY_CARD_MID_NB').change(function () {
            getValuesTpay();
        });

    });

    function getValuesTpay() {

        var tr = $('#fieldset_3_3 .form-wrapper .form-group');
        var id = jQuery("#TPAY_CARD_MID_NB option:selected").val();
        var mid = 0;
        if (id == 1) {
            mid = 3;
        } else {
            mid = (id - 1) * 9 + 3;
        }

        var maxMid = mid + 9;


        for (var n = 3; n < tr.length; n++) {
            tr[n].style.display = "none";
        }
        for (var o = mid; o < maxMid; o++) {
            tr[o].style.display = "";

        }
    }
</script>