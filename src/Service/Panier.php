<?php

namespace App\Service;

class Panier
{
    public function __construct()
    {
        if (!isset($_SESSION)) {
            session_start();
        }
        if (!isset($_SESSION['panier'])) {
            $_SESSION['panier']=array();
            $_SESSION['panier']['id'] = array();
            $_SESSION['panier']['userId'] = array();
            $_SESSION['panier']['libelle'] = array();
            $_SESSION['panier']['qte'] = array();
            $_SESSION['panier']['prixu'] = array();
            $_SESSION['panier']['prix'] = array();
            $_SESSION['panier']['devise'] = array();
            $_SESSION['panier']['dates'] = array();
            $_SESSION['panier']['image'] = array();
            $_SESSION['panier']['adresse'] = array();
            $_SESSION['panier']['email'] = array();
            $_SESSION['panier']['donnees_vendeur'] = array();
            $_SESSION['panier']['name'] = array();
            $_SESSION['panier']['categorie'] = array();
            $_SESSION['panier']['sousCategorie'] = array();
            $_SESSION['panier']['livraison'] = array();
        }
    }


/**
 * Ajoute un article dans le panier
 * @param int $id
 * @param int $userId
 * @param string $libelle
 * @param int $qte
 * @param float $prixu
 * @param float $prix
 * @param string $devise
 * @param string $dates
 * @param string $image
 * @param string $adresse
 * @param string $email
 * @return void
 */
public function ajouterProduit(
    $id,
    $userId,
    $libelle,
    $qte,
    $prixu,
    $prix,
    $devise,
    $dates,
    $image,
    $adresse,
    $email,
    $donnees_vendeur,
    $name,
    $categorie,
    $sousCategorie,
    $livraison = 'oui',
    $etat = ''
)
{
    //Si le panier existe
    $position = array_search($id, $_SESSION['panier']['id']);
    if ($position === false) {
        //Sinon on ajoute
        array_push($_SESSION['panier']['id'], $id);
        array_push($_SESSION['panier']['libelle'], $libelle);
        array_push($_SESSION['panier']['userId'], $userId);
        array_push($_SESSION['panier']['qte'], $qte);
        array_push($_SESSION['panier']['prixu'], $prixu);
        array_push($_SESSION['panier']['prix'], $prixu * $qte);
        array_push($_SESSION['panier']['devise'], '€'/*$devise*/);
        array_push($_SESSION['panier']['dates'], $dates);
        array_push($_SESSION['panier']['image'], $image);
        array_push($_SESSION['panier']['adresse'], $adresse);
        array_push($_SESSION['panier']['email'], $email);
        array_push($_SESSION['panier']['donnees_vendeur'], $donnees_vendeur);
        array_push($_SESSION['panier']['name'], $name);
        array_push($_SESSION['panier']['categorie'], $categorie);
        array_push($_SESSION['panier']['sousCategorie'], $sousCategorie);
        array_push($_SESSION['panier']['livraison'], $livraison);
    } else {
        if ($etat == '' && ($_SESSION['panier']['categorie'][$position] == 'ops_speciale_article' || $_SESSION['panier']['categorie'][$position] == 'produits')) {
            $_SESSION['panier']['qte'][$position] = $qte + $_SESSION['panier']['qte'][$position];
            $_SESSION['panier']['prix'][$position] = $_SESSION['panier']['qte'][$position] * $prixu;
        } elseif ($etat == 'edition' && $_SESSION['panier']['categorie'][$position] == 'produits') {
            $_SESSION['panier']['qte'][$position] = $qte;
            $_SESSION['panier']['prix'][$position] = $_SESSION['panier']['qte'][$position] * $prixu ;
        } else {
            $_SESSION['panier']['qte'][$position] = $qte ;
            $_SESSION['panier']['prix'][$position] = $prix ;
        }
        $_SESSION['panier']['libelle'][$position] = $libelle ;
        $_SESSION['panier']['dates'][$position] = $dates ;
    }
    //$_SESSION['taille']=$this->nombreProduit();
}



/**
 * Modifie la quantité d'un article
 * @param $id
 * @param $qte
 * @return void
 */
public function modifierQTeArticle($id, $qte)
{
    //Si la quantité est positive on modifie sinon on supprime l'article
    if ($qte > 0) {
        //Recherche du dans le panier
        $position = array_search($id, $_SESSION['panier']['id']);

        if ($position !== false) {
            $_SESSION['panier']['qte'][$position] = $qte ;
            $_SESSION['panier']['prix'][$position] = $_SESSION['panier']['qte'][$position]*$_SESSION['panier']['prix'][$position] ;
        }
    }
}

/**
 * Supprime un article du panier
 * @param int $id
 * @return unknown_type
 */
public function supprimerArticle($id)
{
    //Nous allons passer par un panier temporaire
    $tmp=array();
    $tmp['id'] = array();
    $tmp['userId'] = array();
    $tmp['libelle'] = array();
    $tmp['qte'] = array();
    $tmp['prixu'] = array();
    $tmp['prix'] = array();
    $tmp['devise'] = array();
    $tmp['dates'] = array();
    $tmp['image'] = array();
    $tmp['adresse'] = array();
    $tmp['email'] = array();
    $tmp['donnees_vendeur'] = array();
    $tmp['name'] = array();
    $tmp['categorie'] = array();
    $tmp['sousCategorie'] = array();
    $tmp['livraison'] = array();
    for ($i = 0; $i < count($_SESSION['panier']['id']); $i++) {
        if ($_SESSION['panier']['id'][$i] != $id) {
            array_push($tmp['id'], $_SESSION['panier']['id'][$i]);
            array_push($tmp['userId'], $_SESSION['panier']['userId'][$i]);
            array_push($tmp['libelle'], $_SESSION['panier']['libelle'][$i]);
            array_push($tmp['qte'], $_SESSION['panier']['qte'][$i]);
            array_push($tmp['prixu'], $_SESSION['panier']['prixu'][$i]);
            array_push($tmp['prix'], $_SESSION['panier']['prix'][$i]);
            array_push($tmp['devise'], $_SESSION['panier']['devise'][$i]);
            array_push($tmp['dates'], $_SESSION['panier']['dates'][$i]);
            array_push($tmp['image'], $_SESSION['panier']['image'][$i]);
            array_push($tmp['adresse'], $_SESSION['panier']['adresse'][$i]);
            array_push($tmp['email'], $_SESSION['panier']['email'][$i]);
            array_push($tmp['donnees_vendeur'], $_SESSION['panier']['donnees_vendeur'][$i]);
            array_push($tmp['name'], $_SESSION['panier']['name'][$i]);
            array_push($tmp['categorie'], $_SESSION['panier']['categorie'][$i]);
            array_push($tmp['sousCategorie'], $_SESSION['panier']['sousCategorie'][$i]);
            array_push($tmp['livraison'], $_SESSION['panier']['livraison'][$i]);
        }
    }
    //On remplace le panier en session par notre panier temporaire à jour
    $_SESSION['panier'] =  $tmp;
    //On efface notre panier temporaire
    unset($tmp);
    $_SESSION['taille']=$this->nombreProduit();
}


/**
 * Montant total du panier
 * @return string
 */
public function MontantGlobal()
{
    $total=0;
    $produits = $this->listeProduit();
    foreach ($produits as $produit) {
        $total = $total + ($produit['prixu'] * $produit['qte']);
    }
    return $total;
}

/**
 * Montant total du panier
 * @return string
 */
public function MontantGlobalProduits()
{
    $total=0;
    for ($i = 0; $i < count($_SESSION['panier']['id']); $i++) {
        if ($_SESSION['panier']['categorie'][$i] == 'produits' && $_SESSION['panier']['livraison'][$i] == 'oui') {
            $total += $_SESSION['panier']['prix'][$i];
        } elseif ($_SESSION['panier']['categorie'][$i] == 'ops_speciale_article' && $_SESSION['panier']['livraison'][$i] == '1') {
            $total += $_SESSION['panier']['prix'][$i];
        }
    }
    return $total;
}

/**
 * Montant total du panier
 * @return int
 */
public function montant()
{
    $total=0;
    for ($i = 0; $i < sizeof($_SESSION['panier']['id']); $i++) {
        $total += $_SESSION['panier']['prix'][$i];
    }
    return $total;
}

/**
 * Fonction de suppression du panier
 * @return void
 */
public function supprimePanier()
{
    unset($_SESSION['panier']);
}

/**
 * Permet de savoir si le panier est verrouillé
 * @return booleen
 */
public function isVerrouille()
{
    if (isset($_SESSION['panier']) && $_SESSION['panier']['verrou']) {
        return true;
    } else {
        return false;
    }
}

/**
 * Compte le nombre d'articles différents dans le panier
 * @return int
 */
public function nombreProduit()
{
    if (isset($_SESSION['panier'])) {
        return count($_SESSION['panier']['id']);
    } else {
        return 0;
    }
}

/**
 * Compte le nombre d'articles différents dans le panier
 * @return string
 */
public function dates($id)
{
    $dates="";
    if (isset($_SESSION['panier'])) {
        $position = array_search($id, $_SESSION['panier']['id']);
        if ($position !== false) {
            $dates=$_SESSION['panier']['dates'][$position];
        }
    }
    return $dates;
}


/**
 * Liste des produits
 * @return array
 */
public function listeProduit()
{
    $produit=array();

    for ($i = 0; $i < count($_SESSION['panier']['id']); $i++) {
        $produit[]=array(
          'id'=>$_SESSION['panier']['id'][$i],
          'userId'=>$_SESSION['panier']['userId'][$i],
          'libelle'=>$_SESSION['panier']['libelle'][$i],
          'qte'=>$_SESSION['panier']['qte'][$i],
          'prixu'=>$_SESSION['panier']['prixu'][$i],
          'prix'=>$_SESSION['panier']['prix'][$i],
          'devise'=>$_SESSION['panier']['devise'][$i],
          'dates'=>$_SESSION['panier']['dates'][$i],
          'image'=>$_SESSION['panier']['image'][$i],
          'adresse'=>$_SESSION['panier']['adresse'][$i],
          'email'=>$_SESSION['panier']['email'][$i],
          'donnees_vendeur'=>$_SESSION['panier']['donnees_vendeur'][$i],
          'name'=>$_SESSION['panier']['name'][$i],
          'categorie'=>$_SESSION['panier']['categorie'][$i],
          'sousCategorie'=>$_SESSION['panier']['sousCategorie'][$i],
          'livraison'=>$_SESSION['panier']['livraison'][$i]
        );
    }
    return $produit;
}


/**
 * Liste des produits
 * @return array
 */
public function detailProduit($id)
{
    $produit=array();
    $i = array_search($id, $_SESSION['panier']['id']);
    $produit[]=array(
       'id'=>$_SESSION['panier']['id'][$i],
       'userId'=>$_SESSION['panier']['userId'][$i],
       'libelle'=>$_SESSION['panier']['libelle'][$i],
       'qte'=>$_SESSION['panier']['qte'][$i],
       'prix'=>$_SESSION['panier']['prix'][$i],
       'devise'=>$_SESSION['panier']['devise'][$i],
       'dates'=>$_SESSION['panier']['dates'][$i],
       'image'=>$_SESSION['panier']['image'][$i],
       'adresse'=>$_SESSION['panier']['adresse'][$i],
       'email'=>$_SESSION['panier']['email'][$i],
       'donnees_vendeur'=>$_SESSION['panier']['donnees_vendeur'][$i],
       'name'=>$_SESSION['panier']['name'][$i],
       'categorie'=>$_SESSION['panier']['categorie'][$i],
       'sousCategorie'=>$_SESSION['panier']['sousCategorie'][$i],
       'livraison'=>$_SESSION['panier']['livraison'][$i],
      );
    return $produit;
}
}
