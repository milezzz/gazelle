<?
//TODO: Developer, add resend last donation when available AND add missing headers to Test IPN
enforce_login();

/* Include the header  
  if($LoggedUser['RatioWatch']) {
  error('Due to the high volume of paypal disputes, we do not accept donations from users on ratio watch. Sorry.');
  } */

if (!$UserCount = $Cache->get_value('stats_user_count')) {
    $DB->query("SELECT COUNT(ID) FROM users_main WHERE Enabled='1'");
    list($UserCount) = $DB->next_record();
    $Cache->cache_value('stats_user_count', $UserCount, 0); //inf cache
}

$DonorPerms = get_permissions(DONOR);

if ($_GET['miner']) {
    $LoggedUser['BitcoinMiner'] = 1;
    $_GET['showminer'] = 1;
}
show_header('Donate');
?>
<!-- Donate -->
<div class="thin">
    <? /* if (check_perms('site_debug')) { ?>
      <h3>Test IPN</h3>
      <div class="box pad">
      <form method="post" action="donate.php">
      <input type="hidden" name="action" value="ipn">
      <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
      <?=PAYPAL_SYMBOL?> <input type="text" name="mc_gross" value="<?=number_format(PAYPAL_MINIMUM,2)?>">
      <input type="hidden" name="custom" value="<?=$LoggedUser['ID']?>">
      <input type="hidden" name="payment_status" value="Completed">
      <input type="hidden" name="mc_fee" value="0.45">
      <input type="hidden" name="business" value="<?=PAYPAL_ADDRESS?>">
      <input type="hidden" name="txn_id" value="0">
      <input type="hidden" name="payment_type" value="instant">
      <input type="text" name="payer_email" value="<?=$LoggedUser['Username']?>@<?=NONSSL_SITE_URL?>">
      <input type="hidden" name="mc_currency" value="<?=PAYPAL_CURRENCY?>">
      <input name="test" type="submit" value="Donate">
      </form>
      </div>
      <?
      } */ ?>
    <h2>Donate</h2>
    <div class="box pad" style="padding:10px 10px 10px 20px;">
        <p>We accept donations to cover the costs associated with running the site and tracker. These costs come from the rental and purchase of the hardware the site runs on (Servers, Components, etc.), in addition to operating expenses (Bandwidth, Power, etc.).</p>
        <p>Although we have an ad-bar advertisements do not come near to covering our costs, we are reliant upon user donations to make up the shortfall. If you are financially able, please consider making a donation to help us pay the bills!</p>

        <? /*
        <br/>
        <p><strong>method of payment:</strong> use the buttons below to send a message to the Sys-op who will give you directions on how to donate.</p>

          <p>We currently only accept one payment method; PayPal. Because of the fees they charge, there is a <strong>minimum donation amount of <?=PAYPAL_SYMBOL?> <?=PAYPAL_MINIMUM?></strong> (Please note, this is only a minimum amount and we greatly appreciate any extra you can afford.).</b></p>
          <p>You don't have to be a PayPal member to make a donation, you can simply donate with your credit/debit card. If you do not have a credit/debit card, you should be able to donate from your bank account, but you will need to make an account with them to do this.</p>
          <form action="https://www.paypal.com/cgi-bin/webscr" method="post">
          <input type="hidden" name="rm" value="2">
          <input type="hidden" name="cmd" value="_donations">
          <input type="hidden" name="business" value="<?=PAYPAL_ADDRESS?>">
          <input type="hidden" name="return" value="http://<?=SITE_URL?>/donate.php?action=complete">
          <input type="hidden" name="cancel_return" value="http://<?=SITE_URL?>/donate.php?action=cancel">
          <input type="hidden" name="notify_url" value="http://<?=NONSSL_SITE_URL?>/donate.php?action=ipn">
          <input type="hidden" name="item_name" value="Donation">
          <input type="hidden" name="amount" value="">
          <input type="hidden" name="custom" value="<?=$LoggedUser['ID']?>">
          <input type="hidden" name="no_shipping" value="0">
          <input type="hidden" name="no_note" value="1">
          <input type="hidden" name="currency_code" value="<?=PAYPAL_CURRENCY?>">
          <input type="hidden" name="tax" value="0">
          <input type="hidden" name="bn" value="PP-DonationsBF">
          <input type="submit" value="PayPal Donate" />
          </form> */ ?>
    </div>

    <h3>Donate for GB</h3>
    <div class="box pad" style="padding:10px 10px 10px 20px;">
        <p><strong>What you will receive for your donation:</strong></p>
        <ul> 
            <li>You will get 1 GB removed from your download total per &euro; donated</li>  
            <li>For larger donations a more favourable rate may be available, please enquire.</li>  
            <li>If you want to donate for GB please click here: <a href="staffpm.php?action=user_inbox&show=1&msg=donategb">Send a message to sysop</a></li> 
        </ul>
    </div>

    <h3>Donate for love <!-- or 2 BTC minimum donation // please... bitcoin?? --></h3>
    <div class="box pad" style="padding:10px 10px 10px 20px;">
        <p><strong>What you will receive for a suggested minimum 5&euro; donation:</strong></p>
        <ul>
            <? if ($LoggedUser['Donor']) { ?>
                <li>Even more love! (You will not get multiple hearts.)</li>
                <li>A warmer fuzzier feeling than before!</li>
            <? } else { ?>
                <li>Our eternal love, as represented by the <img src="<?= STATIC_SERVER ?>common/symbols/donor.png" alt="Donor" /> you get next to your name.</li>
                <?
                if (USER_LIMIT != 0 && $UserCount >= USER_LIMIT && !check_perms('site_can_invite_always') && !isset($DonorPerms['site_can_invite_always'])) {
                    ?>
                    <li class="warning">Note: Because the user limit has been reached, you will be unable to use the invites received until a later date.</li>
                <? } ?>
                <li>A warm fuzzy feeling.</li> 
            <? } ?>
            <li>If you want to donate for <img src="<?= STATIC_SERVER ?>common/symbols/donor.png" alt="love" title="love" /> please click here: <a href="staffpm.php?action=user_inbox&show=1&msg=donatelove">Send a message to sysop</a></li> 
        </ul>
    </div>
    <h3>What you will <strong>not</strong> receive</h3>
    <div class="box pad" style="padding:10px 10px 10px 20px;">
        <ul>
            <? if ($LoggedUser['Donor']) { ?>
                <li>2 more invitations, these were one time only.</li>
            <? } ?>
            <li>Immunity from the rules.</li>
            <li>Additional upload credit.</li>
        </ul>
        <p>Please be aware that by making a donation you aren't purchasing donor status or invites. You are helping us pay the bills and cover the costs of running the site. We are doing our best to give our love back to donors but sometimes it might take more than 48 hours. Feel free to contact us by sending us a <a href="staffpm.php?action=user_inbox">Staff Message</a> regarding any matter. We will answer as quickly as possible.</p>
    </div>
</div>
<!-- END Donate -->
<? show_footer(); ?>
