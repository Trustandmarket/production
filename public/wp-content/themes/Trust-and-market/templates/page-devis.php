<?php
/*
Template Name: Template Devis
*/

// ini_set('display_errors', '1');
use PHPMailer\PHPMailer\PHPMailer;

$msg = '';
if(isset($_POST['projet']) and !empty($_POST['projet'])){
	
	$projectData  = $_POST['projet'];
	if(!isset($projectData['besoins'])){
		$msg = 'Vous devez choisir au moins un besoin.';
	}
	if($msg === ''){
		$projectRecap = array();
		foreach($projectData as $key => $data){
			if(is_array($data)){
				$data = implode(', ',$data);
			}
			// if($key == 'message'){
				// $data = str_replace('<br>',"\n",nl2br($data));
			// }
			$projectRecap[] = $key.': '.($data);
		}
		// var_dump($projectData);die;
		require_once dirname(__FILE__).'/../vendor/autoload.php';

		$mailer = new PHPMailer(true);
		$user_name  = ucfirst($projectData['prenom']).' '.strtoupper($projectData['nom']);
		$user_email = $projectData['email'];
		// $user_email = 'test@trustandmarket.com';
		
		$admin_name = 'Trust And Market';
		$admin_mail = 'serviceclients@kbr-global.com';
		
		//Sending email to website address
		$mailer->setFrom($user_email, $user_name);
		$mailer->addAddress($admin_mail, $admin_name);
		$mailer->Subject = 'Demande de devis Trust & Market';
		$mailer->isMail();
		$mailer->msgHTML(' ');
		$mailer->AltBody = ' ';
		$mailer->CharSet = 'utf-8';
		$mailer->AddStringAttachment(implode("\r\n\r\n",$projectRecap), 'demande-'.$projectData['prenom'].'_'.$projectData['nom'].'.doc');

		if (!$mailer->send()) {
			$msg = "Une erreur est survenue lors de l'envoi du message, merci de réessayer ou de nous contacter via notre formulaire de contact.". $mailer->ErrorInfo."<script>dataLayer.push({'event':'project'});</script>";
		} else {
			$msg = "Votre demande est envoyée à nos équipes. Nous mettons tout en oeuvre pour vous répondre dans les plus brefs délais.
			<script>dataLayer.push({'event':'project'});</script>";
			$mailer2 = new PHPMailer(true);
			$mailer2->setFrom($admin_mail, $admin_name);
			$mailer2->addAddress($user_email, $user_name);
			$mailer2->Subject = 'Votre projet avec Trust & Market';
			$mailer2->isMail();
			$mailer2->CharSet = 'utf-8';
			$mailer2->msgHTML('<p>Bonjour '.$user_name.',</p> 
						<p>Veuillez trouvez joint au présent le récapitulatif de votre demande. Nous mettons tout en œuvre pour vous répondre dans les plus brefs délais.</p>
						<p>Démarrez dès à présent votre expérience Trust & Market</p>
						<p></p>
						<p style="padding-left:70%">Bien Cordialement,</p>
						<p style="padding-left:70%">Equipe Trust & Market</p>');
			$mailer2->isHTML();
			$mailer2->AltBody = 'Bonjour '.$user_name.', 
						Veuillez trouvez joint au présent le récapitulatif de votre demande. Nous mettons tout en œuvre pour vous répondre dans les plus brefs délais. Démarrez dès à présent votre expérience Trust & Market
						Bien Cordialement,
						Equipe Trust & Market';
			$mailer2->AddStringAttachment(implode("\r\n\r\n",$projectRecap), 'demande-'.$projectData['prenom'].'_'.$projectData['nom'].'.doc');
			$mailer2->send();
			
			// var_dump($mailer2->send(),$mailer2->ErrorInfo);die;
		}
	}
}

// var_dump($mailer);die;
?>

<?php get_template_part('includes/header'); ?>
<div class="menu-background"></div>
<div class="container defaul-template">
  <div class="row">

    <div class="col-xs-12 col-sm-12">
		<div id="content" role="main">
			<?php tha_content_before(); ?>
			<?php get_template_part('includes/loops/content', 'page'); ?>
			<style>
				#your-project-form{padding-bottom:50px;}
				#your-project-form h3{color:#008779;}
				#your-project-form #yourprojectform-text-fields{}
				#your-project-form #yourprojectform-text-fields .form-control{width: 150px;display:inline-block;margin: 2.5px 0;margin-right: 5px;}
				#your-project-form #yourprojectform-text-fields .creneau_range{height: 34px;line-height: 34px;position: relative;top: 0;font-weight: 600;padding: 0 10px;}
				#your-project-form #yourprojectform-text-fields .creneau_range select{width: 40px;padding: 0 5px;height: 32px;line-height: 32px;border: none;margin: 0;-webkit-box-shadow: 0 0;box-shadow: 0 0;background: transparent;font-family: 'Palanquin Dark', "Helvetica Neue", Helvetica, Arial, sans-serif;color: #555 !important;font-size: 14px;-webkit-appearance: none;-moz-appearance: none;-ms-appearance: none;-o-appearance: none;appearance: none;;display:inline-block;}
				#your-project-form #yourprojectform-checboxes{}
				#your-project-form #yourprojectform-checboxes label{display:block;font-size:14px;letter-spacing:-0.4px;margin:0;margin-bottom:10px;}
				#your-project-form #yourprojectform-newsletter-cgu{margin-top:40px;}
				#your-project-form #yourprojectform-message{}
				#your-project-form #yourprojectform-message textarea{height: 300px;padding:25px;}
				#your-project-form button#yourprojectform-submit{text-transform:uppercase;line-height: 45px;height:40px;padding:0 20px;margin:0;}
				#your-project-form #yourprojectform-message-alert{color:#D51D2C;display:block;text-align:center;font-weight:bold;}
			</style>
			<form id="your-project-form" method="POST" class="" onsubmit="return projectFormSubmit();">
				<div id="yourprojectform-text-fields" class="row col-md-12 col-lg-10">
					<h3>Vous et votre projet (* obligatoire)</h3>
					<input type="text" required placeholder="nom*" name="projet[nom]"/>
					<input type="text" required placeholder="prénom*" name="projet[prenom]"/>
					<input type="email" required placeholder="e-mail*" name="projet[email]"/>
					<input type="text" required placeholder="téléphone*" name="projet[telephone]"/>
					<input type="text" required placeholder="votre projet*" name="projet[projet]"/>
					
					<input class="projet-datepicker" type="text" required placeholder="calendrier*" name="projet[calendrier]"/>
					<input type="text" required placeholder="lieu*" name="projet[lieu]"/>
					<input type="hidden" required placeholder="créneau horaire*" id="projet_creneau_horaire" name="projet[creneau_horaire]"/>
					<div class="form-control creneau_range">
						de
						<select class="creneau_start">
							<?php
								for($i=0;$i<24;$i++){
									echo '<option>'.($i<10?'0'.$i:$i).'H</option>';
								}
							?>
						</select>
						à
						<select class="creneau_end">
							<?php
								for($i=0;$i<24;$i++){
									echo '<option>'.($i<10?'0'.$i:$i).'H</option>';
								}
							?>
						</select>
					</div>
					<input type="number" min="1" placeholder="nb de personne" name="projet[nb_de_personne]" class="form-control"/>
					<input type="text" placeholder="votre budget" name="projet[budget]"/>
				</div>
				<div class="row clearfix clear">
					<div id="yourprojectform-checboxes" class="col-md-12 col-lg-6">
						<h3>Vos besoins (minimum une sélection)</h3>
						<div class="row clearfix clear">
							<div class="yourprojectform-besoins-checboxes col-xs-12 col-sm-6 col-md-4">
								<label class="checkboxes-fields">
									<input type="checkbox" name="projet[besoins][]" value="Kit sonorisation"/>
									&nbsp;Kit sonorisation
								</label>
								<label class="checkboxes-fields">
									<input type="checkbox" name="projet[besoins][]" value="Kit lumière"/>
									&nbsp;Kit lumière
								</label>
								<label class="checkboxes-fields">
									<input type="checkbox" name="projet[besoins][]" value="Prestation DJ"/>
									&nbsp;Prestation DJ
								</label>
								<label class="checkboxes-fields">
									<input type="checkbox" name="projet[besoins][]" value="Groupe de musique"/>
									&nbsp;Groupe de musique
								</label>
								<label class="checkboxes-fields">
									<input type="checkbox" name="projet[besoins][]" value="Studio d'enregistrement"/>
									&nbsp;Studio d'enregistrement
								</label>
								<label class="checkboxes-fields">
									<input type="checkbox" name="projet[besoins][]" value="Mixage audio"/>
									&nbsp;Mixage audio
								</label>
								<label class="checkboxes-fields">
									<input type="checkbox" name="projet[besoins][]" value="Matering audio"/>
									&nbsp;Matering audio
								</label>
							</div>
							<div class="yourprojectform-besoins-checboxes col-xs-12 col-sm-6 col-md-4">
								<label class="checkboxes-fields">
									<input type="checkbox" name="projet[besoins][]" value="Cherche musicien"/>
									&nbsp;Cherche musicien
								</label>
								<label class="checkboxes-fields">
									<input type="checkbox" name="projet[besoins][]" value="Production audio"/>
									&nbsp;Production audio
								</label>
								<label class="checkboxes-fields">
									<input type="checkbox" name="projet[besoins][]" value="Chorale gospel"/>
									&nbsp;Chorale gospel
								</label>
								<label class="checkboxes-fields">
									<input type="checkbox" name="projet[besoins][]" value="Shooting photo"/>
									&nbsp;Shooting photo
								</label>
								<label class="checkboxes-fields">
									<input type="checkbox" name="projet[besoins][]" value="Photo mariage"/>
									&nbsp;Photo mariage
								</label>
								<label class="checkboxes-fields">
									<input type="checkbox" name="projet[besoins][]" value="Shooting packshot"/>
									&nbsp;Shooting packshot
								</label>
								<label class="checkboxes-fields">
									<input type="checkbox" name="projet[besoins][]" value="Shooting corporate"/>
									&nbsp;Shooting corporate
								</label>
							</div>
							<div class="yourprojectform-besoins-checboxes col-xs-12 col-sm-6 col-md-4">
								<label class="checkboxes-fields">
									<input type="checkbox" name="projet[besoins][]" value="Tournage clip"/>
									&nbsp;Tournage clip
								</label>
								<label class="checkboxes-fields">
									<input type="checkbox" name="projet[besoins][]" value="Vidéo mariage"/>
									&nbsp;Vidéo mariage
								</label>
								<label class="checkboxes-fields">
									<input type="checkbox" name="projet[besoins][]" value="Film corporate"/>
									&nbsp;Film corporate
								</label>
								<label class="checkboxes-fields">
									<input type="checkbox" name="projet[besoins][]" value="Tournage Ext/Int"/>
									&nbsp;Tournage Ext/Int
								</label>
								<label class="checkboxes-fields">
									<input type="checkbox" name="projet[besoins][]" value="Recherche studio photo"/>
									&nbsp;Recherche studio photo
								</label>
								<label class="checkboxes-fields">
									<input type="checkbox" name="projet[besoins][]" value="Recherche Studio vidéo"/>
									&nbsp;Recherche Studio vidéo
								</label>
								<label id="yourprojectform-other-checbox" class="checkboxes-fields">
									<input type="checkbox" name="projet[besoins][]" value=""/>
									&nbsp;Autres...
								</label>
							</div>
							<div id="yourprojectform-newsletter-cgu" class="col-xs-12 col-sm-12 col-md-12">
								<label class="checkboxes-fields">
									<input type="checkbox" checked name="projet_newsletter" value="1"/>
									&nbsp;J'adhère à la newsletter
								</label>
								<label class="checkboxes-fields">
									<input required type="checkbox" name="projet_cgu" value="1"/>
									&nbsp;En envoyant ma demande, j'accepte les <a href="/nos-conditions/" target="_blank" style="color:#ff7e10;">CGU et la politique de confidentialité et sécurité</a>
								</label>
							</div>
						</div>
					</div>
					<div id="yourprojectform-message" class="col-md-12 col-lg-6">
						<h3>Votre message</h3>
						<textarea class="col-xs-12" name="projet[message]" placeholder="Un message et/ou des précisions concernant votre projet"></textarea>
					</div>
				</div>
				<div class="clearfix clear"></div>
				<div class="row text-right">
					<div id="yourprojectform-message-alert" class="col-lg-10 col-md-8 col-xs-12">
						<?php echo $msg; ?>
					</div>
					<div class="col-lg-2 col-md-4 col-xs-12">
						<button id="yourprojectform-submit" type="submit" class="btn btn-orange edit-btn btn-primary">Envoyer</button>
					</div>
					<div class="clearfix clear"></div>
				</div>
			</form>
		</div><!-- /#content -->
    </div>
    
    <!--<div class="col-xs-6 col-sm-4" id="sidebar" role="navigation">-->
    <!--  <?php get_template_part('includes/sidebar'); ?>-->
    <!--</div>-->
    
  </div><!-- /.row -->
</div><!-- /.container -->

<?php get_template_part('includes/footer'); ?>
