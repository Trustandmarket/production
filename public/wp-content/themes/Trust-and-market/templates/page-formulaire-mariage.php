<?php
/*
Template Name: Template Formulaire Mariage
*/

ini_set('display_errors', '1'); 
use PHPMailer\PHPMailer\PHPMailer;

$msg = '';
if(isset($_POST['wedding']) and !empty($_POST['wedding'])){
	$weddingData  = $_POST['wedding'];
	if(!isset($weddingData['email']) or !$weddingData['email']){
		$msg = '<p class="alert alert-warning">Vous devez entrer votre adresse email</p>';
	}
	if(!isset($weddingData['nom']) or !$weddingData['nom']){
		$msg = '<p class="alert alert-warning">Vous devez entrer votre nom</p>';
	}
	
	if($msg === ''){
		require_once dirname(__FILE__).'/../vendor/autoload.php';
		$mailer = new PHPMailer(true);
		$user_name  = trim(ucfirst($weddingData['prenom']).' '.strtoupper($weddingData['nom']));
		$user_email = $weddingData['email'];
		
		$admin_name = 'Trust And Market';
		$admin_mail = 'contact@kbr-global.com';
		
		//Sending email to admin
		$mailer->setFrom($user_email, $user_name);
		$mailer->addAddress($admin_mail, $admin_name);
		$mailer->Subject = 'Demande devis mariage';
		$mailer->isMail();
		$mailer->CharSet = 'utf-8';
		$mailer->AltBody = 'Ci-dessous les coordonnées du client :
						Nom : '.strtoupper($weddingData['nom']).'
						Prénom : '.ucfirst($weddingData['prenom']).'
						Email : '.$weddingData['email'].'
						Téléphone : '.$weddingData['telephone'].'
						Date : '.$weddingData['date'].'
						Nombre d\'invités : '.$weddingData['nbinv'].'
					Merci,';
		$mailer->isHTML();
		$mailer->msgHTML('<p>Ci-dessous les coordonnées du client :</p>
						<p></p>
						<p>Nom : '.strtoupper($weddingData['nom']).'</p>
						<p>Prénom : '.ucfirst($weddingData['prenom']).'</p>
						<p>Email : '.$weddingData['email'].'</p>
						<p>Téléphone : '.$weddingData['telephone'].'</p>
						<p>Date : '.$weddingData['date'].'</p>
						<p>Nombre d\'invités : '.$weddingData['nbinv'].'</p>
					<p></p>
					<p>Merci,</p>');
		$mailer->CharSet = 'utf-8';
		// $mailer->AddStringAttachment(implode("\r\n\r\n",$projectRecap), 'demande-'.$weddingData['prenom'].'_'.$weddingData['nom'].'.doc');

		if (!$mailer->send()) {
			$msg = '<p class="alert alert-danger">Une erreur est survenue lors de l\'envoi du message, merci de réessayer ou de nous contacter via notre formulaire de contact. '. $mailer->ErrorInfo.'</p>';
		} else {
			$msg = '<p class="alert alert-success">Votre demande est envoyée à nos équipes. Nous mettons tout en oeuvre pour vous répondre dans les plus brefs délais.</p><script>dataLayer.push({"event":"lp_mariage"});</script>';
			$mailer2 = new PHPMailer(true);
			$mailer2->setFrom($admin_mail, $admin_name);
			$mailer2->addAddress($user_email, $user_name);
			$mailer2->Subject = 'Votre mariage avec Trust & Market';
			$mailer2->isMail();
			$mailer2->CharSet = 'utf-8';
			$mailer2->msgHTML('<p>Bonjour '.$user_name.',</p> 
						<p></p>
						<p>Nous avons bien reçu votre demande d’informations. Nous vous contactons dans les plus brefs délais. </p>
						<p></p>
						<p style="padding-left:70%">Bien Cordialement,</p>
						<p style="padding-left:70%">Equipe Trust & Market</p>');
			$mailer2->isHTML();
			$mailer2->AltBody = 'Bonjour '.$user_name.', 
			
						Nous avons bien reçu votre demande d’informations. Nous vous contactons dans les plus brefs délais.
						
						Bien Cordialement,
						Equipe Trust & Market';
			// $mailer2->AddStringAttachment(implode("\r\n\r\n",$projectRecap), 'demande-'.$weddingData['prenom'].'_'.$weddingData['nom'].'.doc');
			$mailer2->send();
			
			// var_dump($mailer2->send(),$mailer2->ErrorInfo);die;
			// @unset($weddingData);
			$weddingData = array();
		}
	}
}

// var_dump($mailer);die;
?>

<?php get_template_part('includes/header'); ?>
<?php $featured_img_url = get_the_post_thumbnail_url(get_the_ID(),'full'); ?>
<div class="menu-background"></div>
<style>
	footer.container-fluid.site-footer {
		bottom: 7px !important;
		position: relative;
	}
	nav.navbar.meganav {
		border: none;
	}
	#wedding-form-block {
		<?php echo (($featured_img_url != '') ? 'background-image:url('.$featured_img_url.');' : ''); ?>
		background-repeat: no-repeat;
		background-size: cover;
		background-position: center;
		padding-top: 10px;
		position: relative;
		top: -2px;
	}
	.wedding-form-template {
		color: #fff;
		font-size: 24px;
		min-height: 420px;
		padding: 280px 0;
		padding-bottom: 190px;
	}
	#yourweddingform-text-block {
		
	}
	#yourweddingform-text-fields {
		float: right;
		float: right;
		background: rgba(0,0,0,0.5);
		padding: 15px;
		margin-top: -15px;
		border-radius: 5px;
	}
	.wedding-form-title p,
	#your-wedding-form p {
		font-size: 25px;
		padding: 25px;
		font-weight: 600;
	}
	.wedding-form-title p {
		color: #fff;
		line-height: 1.2;
		margin: 0;
		padding: 0;
	}
	#your-wedding-form .btn {
		border-radius: 0;
		line-height: 40px;
		height: 40px;
		padding: 0;
		margin: 0;
		margin-top: 15px;
		text-transform: uppercase;
		width: 160px;
	}
	#your-wedding-form{
		
	}
	.wedding-form-title h1 {
		text-align: center;
		color: #ff7e10;
		font-size: 40px;
	}
	#your-wedding-form h3 {
		text-align: center;
		color: #ff7e10;
		font-size: 30px;
		margin: 0 0 30px 0;
	}
	#your-wedding-form label {
		font-size: 22px;
		font-weight: normal;
	}
	#your-wedding-form #yourweddingform-text-fields .form-control {
		width: 100%;
		height: 40px;
		display: inline-block;
		margin: 0;
		margin-bottom: 10px;
		border-radius: 0;
	}
	#your-wedding-form p.alert {
		font-size: 13px;
		padding: 10px 15px !important;
		font-weight: 100;
		text-align: center;
	}
	@media only screen and (max-width: 780px){
		.wedding-form-template {
			position: relative;
			/*padding: 200px 0 50px 0;*/
		}
	}
	@media only screen and (max-width: 480px){
		#your-wedding-form label {
			width: 100%;
		}
	}
</style>
<div id="wedding-form-block">
	<div class="wedding-form-title text-center">
		<h1>Devis en ligne, immédiat et sans engagement</h1>
		<p>Reportage photo, reportage vidéo, sonorisation,<br/>dj mix, chorale gospel, éclairage, salles,<br/>traiteurs, orchestre, event manager</p>
	</div>
	<div class="wedding-form-template">
	  <div class="row">
		<div class="col-xs-12 col-sm-12">
			<div id="content" role="main">
				<form id="your-wedding-form" method="POST" class="row clearfix clear">
					<div id="yourweddingform-text-block" class="col-xs-12 col-md-6 col-lg-4 text-center">
						<?php the_content(); ?>
					</div>
					<div id="yourweddingform-text-fields" class="col-xs-12 col-md-6 col-lg-4">
						<h3>Un partenaire unique,<br/>une offre globale</h3>
						<?php echo $msg; ?>
						<label class="col-xs-6 col-lg-6">
							Nom*
							<br/>
							<input type="text" required name="wedding[nom]" value="<?php echo isset($weddingData['nom'])?$weddingData['nom']:''; ?>" />
						</label>
						<label class="col-xs-6 col-lg-6">
							Prénom
							<br/>
							<input type="text" name="wedding[prenom]" value="<?php echo isset($weddingData['prenom'])?$weddingData['prenom']:''; ?>" />
						</label>
						<label class="col-xs-6 col-lg-6">
							Email*
							<br/>
							<input type="email" required name="wedding[email]" value="<?php echo isset($weddingData['email'])?$weddingData['email']:''; ?>" />
						</label>
						<label class="col-xs-6 col-lg-6">
							Tél.
							<br/>
							<input type="text" name="wedding[telephone]" value="<?php echo isset($weddingData['telephone'])?$weddingData['telephone']:''; ?>" />
						</label>
						<label class="col-xs-6 col-lg-6">
							Date
							<br/>
							<input type="text" name="wedding[date]" value="<?php echo isset($weddingData['date'])?$weddingData['date']:''; ?>" />
						</label>
						<label class="col-xs-6 col-lg-6">
							Nb. invités
							<br/>
							<input type="text" name="wedding[nbinv]" value="<?php echo isset($weddingData['nbinv'])?$weddingData['nbinv']:''; ?>" />
						</label>
						<div class="col-lg-12 col-xs-12 text-center">
							<input type="submit" value="Envoyer" class="btn btn-orange btn-lg edit-btn"/>
						</div>
					</div>
				</form>
			</div><!-- /#content -->
		</div>
	  </div><!-- /.row -->
	</div><!-- /.wedding-form-template -->
</div>

<?php get_template_part('includes/footer'); ?>
