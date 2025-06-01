<?php

namespace App\Service;

use App\Entity\User;
use MangoPay;
use MangoPay\{Address,
    BankAccount,
    CardRegistration,
    Hook,
    KycDocument,
    KycDocumentStatus,
    KycPage,
    LegalPersonType,
    Money,
    PayIn,
    PayInExecutionDetailsWeb,
    PayInExecutionType,
    PayInPaymentDetailsCard,
    PayInPaymentType,
    PayOut,
    PayOutPaymentDetailsBankWire,
    PayOutPaymentType,
    Transfer,
    UserLegal,
    Wallet
};
use phpDocumentor\Reflection\Types\Array_;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use MangoPay\Libraries\ResponseException as MGPResponseException;
use MangoPay\Libraries\Exception as MGPException;
use Throwable;

class Payment
{
    private $mangoPayApi;
    private $httpClient;
    private $apiKeys;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->mangoPayApi = new MangoPay\MangoPayApi();
        //$this->mangoPayApi->AuthenticationManager
        $this->mangoPayApi->Config->ClientId = $_SERVER['MANGOPAY_CLIENT'];
        $this->mangoPayApi->Config->ClientPassword = $_SERVER['MANGOPAY_PASSWORD'];
        $this->mangoPayApi->Config->BaseUrl = $_SERVER['MANGOPAY_BASE_URL'];
        $this->mangoPayApi->Config->TemporaryFolder = $_SERVER['DOCUMENT_ROOT'];
        $this->httpClient = $httpClient;
        $this->apiKeys = $_SERVER['STRIPE_API_PROD'];
    }


    public function createMangoUser(
        $type,
        $legalPersonType,
        $firstName,
        $lastName,
        $birthday,
        $nationality,
        $CountryOfResidence,
        $email
    )
    {
        // $mangoUser = '';
        if ($type == 'UserLegal') {
            $user = new UserLegal();
            $user->Name = $firstName . ' ' . $lastName;
            $user->Email = $email;
            $user->LegalPersonType = LegalPersonType::Business;

            $address = new Address();
            $address->AddressLine1 = 'adresse_temp';
            $address->City = 'ville_temp';
            $address->Country = 'FR';
            $address->PostalCode = '75011';
            $address->Region = 'IDF';

            $user->HeadquartersAddress = $address;
            $user->LegalRepresentativeAddress = $address;

            $user->LegalRepresentativeFirstName = $firstName;
            $user->LegalRepresentativeLastName = $lastName;
            $user->LegalRepresentativeEmail = $email;
            $user->LegalRepresentativeBirthday = $birthday;
            $user->LegalRepresentativeNationality = $nationality;
            $user->LegalRepresentativeCountryOfResidence = $CountryOfResidence;
            $user->CompanyNumber = 'TEMP00000';

            $user->Tag = $legalPersonType;

            $user->TermsAndConditionsAccepted = true;
            $user->UserCategory = 'OWNER';

            return $this->mangoPayApi->Users->Create($user);
        }

        if ($type == 'UserNatural') {
            $user = new MangoPay\UserNatural();
            $user->Email = $email;
            $user->FirstName = $firstName;
            $user->LastName = $lastName;
            $user->Birthday = $birthday;
            $user->Nationality = $nationality;
            $user->CountryOfResidence = $CountryOfResidence;
            //$user->TermsAndConditionsAccepted = true;
            $user->UserCategory = 'PAYER';

            return $this->mangoPayApi->Users->Create($user);
        }
    }


    public function createDocumentMangoUser($userId, $type, $file)
    {
        try {
            // $UserId = $userId;

            //create the doc
            $KycDocument = new KycDocument();
            $KycDocument->Type = $type;
            $result = $this->mangoPayApi->Users->CreateKycDocument(
                $userId,
                $KycDocument
            );
            $KycDocumentId = $result->Id;

            //add a page to this doc
            $KycPage = new KycPage();
            $KycPage->File = $file;

            // $Result = $Api->Users->CreateKycPageFromFile($UserId, $KYCDocumentId, $KycPage);
            $result = $this->mangoPayApi->Users->CreateKycPageFromFile(
                $userId,
                $KycDocumentId,
                $file
            );

            //submit the doc for validation
            $KycDocument = new KycDocument();
            $KycDocument->Id = $KycDocumentId;
            $KycDocument->Status = KycDocumentStatus::ValidationAsked;
            $result2 = $this->mangoPayApi->Users->UpdateKycDocument(
                $userId,
                $KycDocument
            );

            return $KycDocumentId;

        } catch (MangoPay\Libraries\ResponseException $e) {
            $e->GetErrorDetails();
        } catch (MangoPay\Libraries\Exception $e) {
            $e->GetMessage();
            return null;
        }
    }


    public function viewDocumentMangoUser($id)
    {
        $KycDocument = $this->mangoPayApi->KycDocuments->Get($id);
        try {
            return $KycDocument;
        } catch (MangoPay\Libraries\ResponseException $e) {
            // handle/log the response exception with code $e->GetCode(), message $e->GetMessage() and error(s)
            return $e->GetErrorDetails();
        }
    }

    public function updateUserLegal($userId, $type, $data)
    {
        if ($type == "UserNatural") {
            $UserNatural = $this->mangoPayApi->Users->Get($userId);

            $UserNatural->FirstName = $data["firstname"];
            $UserNatural->LastName = $data["lastname"];
            //$UserNatural->Birthday = $data["birthday"];
            //$UserNatural->Nationality = $data["nationality"];
            $UserNatural->CountryOfResidence = $data["countryOfResidence"];
            $UserNatural->Email = $data["user_email"];
            $UserNatural->UserCategory = "PAYER";
            $UserNatural->TermsAndConditionsAccepted = true;
            //La region est ob pour Usa, Canada Ca, MX Mexique
            if ($data["user_address_1"] != '' && $data["user_city"] && $data["user_country"] != '' && $data["user_postcode"] != '') {
                $UserNatural->Address = new Address();
                $UserNatural->Address->AddressLine1 = $data["user_address_1"];
                $UserNatural->Address->City = $data["user_city"];
                $UserNatural->Address->Country = $data["user_country"];
                $UserNatural->Address->PostalCode = $data["user_postcode"];
            }
            return $this->mangoPayApi->Users->Update($UserNatural);
        } else {
            $user = $this->mangoPayApi->Users->Get($userId);
            if ($data["compagny_name"] != '') {
                $user->Name = $data["compagny_name"];
            }
            if ($data["billing_email"] != '') {
                $user->Email = $data["billing_email"];
            }
            if ($data["siret"] != '') {
                $user->CompanyNumber = $data["siret"];
            }
            $user->LegalPersonType = LegalPersonType::Business;

            if ($data["user_address_1"] != '' && $data["user_city"] != '' && $data["user_country"] != '' && $data["user_postcode"] != '') {
                $address = new Address();
                $address->AddressLine1 = $data["user_address_1"];
                $address->City = $data["user_city"];
                $address->Country = $data["user_country"];
                $address->PostalCode = $data["user_postcode"];
                $address->Region = $data["region"];
                $user->LegalRepresentativeAddress = $address;
            }

            if ($data["billing_address_1"] != '' && $data["billing_city"] != '' && $data["billing_country"] != '' && $data["billing_postcode"] != '' && $data["billing_state"] != '') {
                $address = new Address();
                $address->AddressLine1 = $data["billing_address_1"];
                $address->City = $data["billing_city"];
                $address->Country = $data["billing_country"];
                $address->PostalCode = $data["billing_postcode"];
                $address->Region = $data["billing_state"];
                $user->HeadquartersAddress = $address;
            }

            $user->LegalRepresentativeFirstName = $data["firstname"];
            $user->LegalRepresentativeLastName = $data["lastname"];
            $user->LegalRepresentativeEmail = $data["user_email"];
            $user->LegalRepresentativeBirthday = $data["birthday"];
            $user->LegalRepresentativeNationality = $data["nationality"];
            $user->LegalRepresentativeCountryOfResidence = $data["countryOfResidence"];

            $user->Tag = 'Updated using Mangopay PHP SDK';
            $user->TermsAndConditionsAccepted = true;
            $user->UserCategory = "OWNER"; //En verification
            $createdUser = null;
            if ($data["compagny_name"] != '' && $data["siret"] != '' && $data["billing_address_1"] != '' &&
                $data["billing_city"] != '' && $data["billing_country"] != '' && $data["billing_postcode"] != '' &&
                $data["billing_state"] != '') {
                $createdUser = $this->mangoPayApi->Users->Update($user);
            }

            return $createdUser;
        }
    }

    public function createMangoUserLegal($data)
    {
        $user = new UserLegal();
        $user->Name = $data["compagny_name"];
        $user->Email = $data["billing_email"];
        $user->LegalPersonType = LegalPersonType::Business;

        $address = new Address();
        $address->AddressLine1 = $data["user_address_1"];
        $address->City = $data["user_city"];
        $address->Country = $data["user_country"];
        $address->PostalCode = $data["user_postcode"];
        $address->Region = $data["region"];
        $user->LegalRepresentativeAddress = $address;

        $address = new Address();
        $address->AddressLine1 = $data["billing_address_1"];
        $address->City = $data["billing_city"];
        $address->Country = $data["billing_country"];
        $address->PostalCode = $data["billing_postcode"];
        $address->Region = $data["billing_state"];
        $user->HeadquartersAddress = $address;

        $user->LegalRepresentativeFirstName = $data["firstname"];
        $user->LegalRepresentativeLastName = $data["lastname"];
        $user->LegalRepresentativeEmail = $data["user_email"];
        $user->LegalRepresentativeBirthday = $data["birthday"];
        $user->LegalRepresentativeNationality = $data["nationality"];
        $user->LegalRepresentativeCountryOfResidence = $data["countryOfResidence"];
        $user->CompanyNumber = $data["siret"];

        $user->Tag = $data["firstname"] . ' ' . $data["compagny_name"];
        $user->TermsAndConditionsAccepted = true;
        $user->UserCategory = "OWNER";

        return $this->mangoPayApi->Users->Create($user);
    }

    public function updateUserLegalBilling($userId, $donnees)
    {
        $userLegal = $this->mangoPayApi->Users->Get($userId);
        $userLegal->Name = $donnees["compagny_name"];
        $userLegal->Email = $donnees["email"];
        return $this->mangoPayApi->Users->Update($userLegal);
    }


    public function getStripeUserAccount($accountId)
    {
        $url = "https://api.stripe.com/v1/accounts/$accountId";
        return $this->executeCurlRequest($url, [], 'GET');
    }

    public function isUserKycValidated($userId, $roles)
    {
        $identite = null;
        $enregistrement = null;
        $statuts = null;
        $shareholder = null;
        $response = false;
        if (in_array('ROLE_AUTO_ENTREPRENEUR', $roles) || in_array('ROLE_SOCIETE', $roles)) {
            $identite = $this->isDocumentSubmited($userId, 'IDENTITY_PROOF');
            $enregistrement = $this->isDocumentSubmited($userId, 'REGISTRATION_PROOF');
            if ($identite && sizeof($identite) > 0) {
                $identite = array_shift($identite);
                if ($identite->Status == "VALIDATED") {
                    $identite = true;
                } else {
                    $identite = false;
                }
            }
            if ($enregistrement && sizeof($enregistrement) > 0) {
                $enregistrement = array_shift($enregistrement);
                if ($enregistrement->Status == "VALIDATED") {
                    $enregistrement = true;
                } else {
                    $enregistrement = false;
                }
            }
            if ($identite && $enregistrement) {
                $response = true;
            }
        }
        if (in_array('ROLE_SOCIETE', $roles)) {
            $statuts = $this->isDocumentSubmited($userId, 'ARTICLES_OF_ASSOCIATION');
            $shareholder = $this->isDocumentSubmited($userId, 'SHAREHOLDER_DECLARATION');
            $response = false;
            if ($statuts && sizeof($statuts) > 0) {
                $statuts = array_shift($statuts);
                if ($statuts->Status == "VALIDATED") {
                    $statuts = true;
                } else {
                    $statuts = false;
                }
            }
            if ($shareholder && sizeof($shareholder) > 0) {
                $shareholder = array_shift($shareholder);
                if ($shareholder->Status == "VALIDATED") {
                    $shareholder = true;
                } else {
                    $shareholder = false;
                }
            }
            if ($identite && $enregistrement && $statuts && $shareholder) {
                $response = true;
            }
            return $response;
        }

        return $response;
    }

    public function isDocumentSubmited($userId, $type, $status = null)
    {

        try {
            $filter_class = new MangoPay\FilterKycDocuments();
            $filter_class->Type = $type;
            if ($status) {
                $filter_class->Status = $status;
            }
            $sorting = new MangoPay\Sorting();
            $sorting->AddField("CreationDate", "DESC");
            $response = $this->mangoPayApi->KycDocuments->GetAll($userId, $sorting, $filter_class);
            return $response;
        } catch (Exception $e) {
            return $e;
        }
    }

    public function getWallet($idWallet)
    {
        try {
            return $this->mangoPayApi->Wallets->Get($idWallet);
        } catch (Exception $e) {
            return null;
        }
    }

    public function getOrCreateUserWallet($userId, $description)
    {
        //Check if user has wallet
        $userWallets = $this->getUserAbonneWalletsObjects($userId);
        $walletId = null;
        if ($userWallets == null || sizeof($userWallets) == 0) {
            $userWallets = $this->createWallet($userId, $description, 'EUR');
            if ($userWallets) {
                $walletId = $userWallets->Id;
            }
        } elseif ($userWallets && sizeof($userWallets) > 0) {
            $walletId = $userWallets['0']->Id;
        }
        return $walletId;
    }

    public function getUserAbonneWalletsObjects($userId)
    {
        try {
            $sorting = new MangoPay\Sorting();
            $sorting->AddField("Id", "DESC");
            $pagination = new MangoPay\Pagination();
            $pagination->ItemsPerPage = 20;
            return $this->mangoPayApi->Users->GetWallets($userId);
        } catch (Exception $e) {
            return null;
        }
    }

    public function createWallet($idOwner, $description, $devise)
    {
        $Wallet = new Wallet();
        $Wallet->Owners = array($idOwner);
        $Wallet->Description = $description;
        $Wallet->Currency = $devise;
        $Wallet->Tag = 'custom meta';

        //$result = $this->mangoPayApi->Wallets->Create($Wallet);
        return $this->mangoPayApi->Wallets->Create($Wallet);
    }

    public function doPayInCardWeb($idWallet, $idAuthor, $cardType, $devise, $amount, $lang, $returnUrl, $seller_data = null, $delivery_fee = 0)
    {
        $amount = str_replace(' ', '', $amount);
        $PayIn = new PayIn();
        $PayIn->CreditedWalletId = $idWallet;
        $PayIn->AuthorId = $idAuthor;
        $PayIn->Tag = 'Vendeurs:' . $seller_data;
        $PayIn->PaymentType = PayInPaymentType::Card;
        $PayIn->PaymentDetails = new PayInPaymentDetailsCard();
        $PayIn->PaymentDetails->CardType = $cardType;
        $PayIn->DebitedFunds = new Money();
        $PayIn->DebitedFunds->Currency = $devise;
        $PayIn->DebitedFunds->Amount = $amount;
        $PayIn->Fees = new Money();
        $PayIn->Fees->Currency = $devise;
        $PayIn->Fees->Amount = 0.9 * $delivery_fee + 0.1 * $amount;
        $PayIn->ExecutionType = PayInExecutionType::Web;
        $PayIn->ExecutionDetails = new PayInExecutionDetailsWeb();
        $PayIn->ExecutionDetails->ReturnURL = $returnUrl;
        $PayIn->ExecutionDetails->Culture = $lang;
        $PayIn->SecureMode = 'DEFAULT';
        $result = $this->mangoPayApi->PayIns->Create($PayIn);
        return $result;
    }

    public function transferBetweenWallet(
        $authorId,
        $creditedUserId,
        $amountCurrency,
        $amount,
        $feeCurrency,
        $debitedWalletId,
        $creditedWalletId
    )
    {
        try {
            $transfer = new Transfer();
            $transfer->AuthorId = $authorId;
            $transfer->CreditedUserId = $creditedUserId;
            $transfer->Tag = 'Created using Mangopay PHP SDK';
            $transfer->DebitedFunds = new Money();
            $transfer->DebitedFunds->Currency = $amountCurrency;
            $transfer->DebitedFunds->Amount = $amount;
            $transfer->Fees = new Money();
            $transfer->Fees->Currency = $feeCurrency;
            $transfer->Fees->Amount = 0;
            $transfer->CreditedWalletId = $creditedWalletId;
            $transfer->DebitedWalletId = $debitedWalletId;

            return $this->mangoPayApi->Transfers->Create($transfer);
        } catch (Exception $e) {
            return null;
        }
    }

    public function checkNotificationExistence($event, $url)
    {
        try {
            $Hook = $this->mangoPayApi->Hooks->Get();
            for ($i = 0; $i < sizeof($Hook); $i++) {
                if (
                    $Hook[$i]->Status == 'ENABLED' &&
                    $Hook[$i]->EventType == $event &&
                    $Hook[$i]->Validity == 'VALID'
                ) {
                    return true;
                } else {
                    $this->NotificationsPush($event, $url);
                }
            }
        } catch (Exception $e) {
            return false;
        }
    }

    public function NotificationsPush($event, $url)
    {
        try {
            $Hook = new Hook();
            $Hook->EventType = $event;
            $Hook->Url = $url;

            $this->mangoPayApi->Hooks->Create($Hook);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function createBankAccount(
        $type,
        $accountNumber,
        $detailsBic,
        $ABA,
        $DepositAccountType,
        $BranchCode,
        $InstitutionNumber,
        $BankName,
        $SortCode,
        $countryAccount,
        $ownerName,
        $address1,
        $address2,
        $country,
        $city,
        $postalCode,
        $region,
        $userId
    )
    {
        $BankAccount = new BankAccount();

        if ($type == 'IBAN' && $accountNumber != '' && $detailsBic != '') {
            $BankAccount->Type = $type;
            $BankAccount->OwnerName = $ownerName;
            $BankAccount->Details = new MangoPay\BankAccountDetailsIBAN();
            $BankAccount->Details->IBAN = $accountNumber;
            $BankAccount->Details->BIC = $detailsBic;
            $BankAccount->OwnerAddress = new Address();
            $BankAccount->OwnerAddress->AddressLine1 = $address1;
            $BankAccount->OwnerAddress->AddressLine2 = $address2;
            $BankAccount->OwnerAddress->City = $city;
            $BankAccount->OwnerAddress->Country = $country;
            $BankAccount->OwnerAddress->PostalCode = $postalCode;
            $BankAccount->OwnerAddress->Region = $region;
            $result = $this->mangoPayApi->Users->CreateBankAccount($userId, $BankAccount);
            return $result->Id;
        }
        if ($type == 'US' && $ABA != '' && $accountNumber != '' && $DepositAccountType != '') {
            $BankAccount->Type = $type;
            $BankAccount->OwnerName = $ownerName;
            $BankAccount->Details = new MangoPay\BankAccountDetailsUS();
            $BankAccount->Details->ABA = $ABA;
            $BankAccount->Details->AccountNumber = $accountNumber;
            $BankAccount->Details->DepositAccountType = $DepositAccountType;
            $BankAccount->OwnerAddress = new Address();
            $BankAccount->OwnerAddress->AddressLine1 = $address1;
            $BankAccount->OwnerAddress->AddressLine2 = $address2;
            $BankAccount->OwnerAddress->City = $city;
            $BankAccount->OwnerAddress->Country = $country;
            $BankAccount->OwnerAddress->PostalCode = $postalCode;
            $BankAccount->OwnerAddress->Region = $region;
            $result = $this->mangoPayApi->Users->CreateBankAccount($userId, $BankAccount);
            return $result->Id;
        }
        if ($type == 'CA' && $accountNumber != '' && $BankName != '' && $BranchCode != '' && $InstitutionNumber != '') {
            $BankAccount->Type = $type;
            $BankAccount->OwnerName = $ownerName;
            $BankAccount->Details = new MangoPay\BankAccountDetailsCA();
            $BankAccount->Details->AccountNumber = $accountNumber;
            $BankAccount->Details->BankName = $BankName;
            $BankAccount->Details->BranchCode = $BranchCode;
            $BankAccount->Details->InstitutionNumber = $InstitutionNumber;
            $BankAccount->OwnerAddress = new Address();
            $BankAccount->OwnerAddress->AddressLine1 = $address1;
            $BankAccount->OwnerAddress->AddressLine2 = $address2;
            $BankAccount->OwnerAddress->City = $city;
            $BankAccount->OwnerAddress->Country = $country;
            $BankAccount->OwnerAddress->PostalCode = $postalCode;
            $BankAccount->OwnerAddress->Region = $region;
            $result = $this->mangoPayApi->Users->CreateBankAccount($userId, $BankAccount);
            return $result->Id;
        }
        if ($type == 'GB' && $accountNumber != '' && $SortCode != '') {
            $BankAccount->Type = $type;
            $BankAccount->OwnerName = $ownerName;
            $BankAccount->Details = new MangoPay\BankAccountDetailsGB();
            $BankAccount->Details->AccountNumber = $accountNumber;
            $BankAccount->Details->SortCode = $SortCode;
            $BankAccount->OwnerAddress = new Address();
            $BankAccount->OwnerAddress->AddressLine1 = $address1;
            $BankAccount->OwnerAddress->AddressLine2 = $address2;
            $BankAccount->OwnerAddress->City = $city;
            $BankAccount->OwnerAddress->Country = $country;
            $BankAccount->OwnerAddress->PostalCode = $postalCode;
            $BankAccount->OwnerAddress->Region = $region;
            $result = $this->mangoPayApi->Users->CreateBankAccount($userId, $BankAccount);
            return $result->Id;
        }
        if ($type == 'autre' && $accountNumber != '' && $detailsBic != '' && $countryAccount != '') {
            $BankAccount->Type = $type;
            $BankAccount->OwnerName = $ownerName;
            $BankAccount->Details = new MangoPay\BankAccountDetailsOTHER();
            $BankAccount->Details->AccountNumber = $accountNumber;
            $BankAccount->Details->BIC = $detailsBic;
            $BankAccount->Details->Country = $countryAccount;

            $BankAccount->OwnerAddress = new Address();
            $BankAccount->OwnerAddress->AddressLine1 = $address1;
            $BankAccount->OwnerAddress->AddressLine2 = $address2;
            $BankAccount->OwnerAddress->City = $city;
            $BankAccount->OwnerAddress->Country = $country;
            $BankAccount->OwnerAddress->PostalCode = $postalCode;
            $BankAccount->OwnerAddress->Region = $region;
            $result = $this->mangoPayApi->Users->CreateBankAccount($userId, $BankAccount);
            return $result->Id;
        }
        return true;
    }

    public function payOut(
        $authorId,
        $debitedWalletId,
        $currency,
        $amount,
        $feeCurrency,
        $fee,
        $bankAccountId
    )
    {
        $PayOut = new PayOut();
        $PayOut->AuthorId = $authorId;
        $PayOut->DebitedWalletId = $debitedWalletId;
        $PayOut->DebitedFunds = new Money();
        $PayOut->DebitedFunds->Currency = $currency;
        $PayOut->DebitedFunds->Amount = $amount;
        $PayOut->Fees = new Money();
        $PayOut->Fees->Currency = $feeCurrency;
        $PayOut->Fees->Amount = $fee;
        $PayOut->PaymentType = PayOutPaymentType::BankWire;
        $PayOut->MeanOfPaymentDetails = new PayOutPaymentDetailsBankWire();
        $PayOut->MeanOfPaymentDetails->BankAccountId = $bankAccountId;
        $result = $this->mangoPayApi->PayOuts->Create($PayOut);

        return $result->Id;
    }

    public function getTransaction($id)
    {
        try {
            return $this->mangoPayApi->PayIns->Get($id);
        } catch (Exception $e) {
            return null;
        }
    }

    public function createCardRegistration($userId, $cardType, $tag, $currency = 'EUR')
    {
        $cardRegistration = new CardRegistration();
        $cardRegistration->UserId = $userId;
        $cardRegistration->CardType = $cardType;
        $cardRegistration->Currency = $currency;
        $cardRegistration->Tag = $tag;

        return $this->mangoPayApi->CardRegistrations->Create($cardRegistration);
    }

    public function updateCardRegistration($cardId, $data, $tag)
    {
        $cardRegistration = new CardRegistration();
        $cardRegistration->Id = $cardId;
        $cardRegistration->RegistrationData = $data;
        $cardRegistration->Tag = $tag;

        return $this->mangoPayApi->CardRegistrations->Update($cardRegistration);
    }

    public function getCardRegistration($cardId)
    {

        return $this->mangoPayApi->CardRegistrations->Get($cardId);
    }

    public function getAuthToken(): string
    {
        $credentials = base64_encode("{$this->mangoPayApi->getConfig()->ClientId}:{$this->mangoPayApi->getConfig()->ClientPassword}");
        $response = $this->httpClient->request('POST', $this->mangoPayApi->getConfig()->BaseUrl . '/v2.01/oauth/token', [
            'headers' => [
                'Authorization' => "Basic $credentials",
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => [
                'grant_type' => 'client_credentials',
            ],
        ]);

        $data = $response->toArray();
        return $data['access_token'];
    }

    public function createRecurringPayin(string $accessToken, $authorId, $creditedUserId, $creditedWalletId, $cardId, $amount, $fee, $reccuringAmount, $user, $endDate): array
    {
        $apiUrl = $this->mangoPayApi->getConfig()->BaseUrl . "/v2.01/{$this->mangoPayApi->getConfig()->ClientId}/recurringpayinregistrations";

        $body = [
            "AuthorId" => $authorId,
            "CardId" => $cardId,
            "CreditedUserId" => $creditedUserId,
            "CreditedWalletId" => $creditedWalletId,
            "FirstTransactionDebitedFunds" => [
                "Currency" => "EUR",
                "Amount" => $amount
            ],
            "FirstTransactionFees" => [
                "Currency" => "EUR",
                "Amount" => $fee
            ],
            //"EndDate" => $endDate,
            "Frequency" => "Weekly",
            "FixedNextAmount" => false,
            "FractionedPayment" => true,
            "FreeCycles" => 0,
            "NextTransactionDebitedFunds" => [
                "Currency" => "EUR",
                "Amount" => $reccuringAmount
            ],
            "Billing" => [
                "FirstName" => "",
                "LastName" => $user->getInformationsFacturationUtilisateur()->getNomOuSociete(),
                "Address" => [
                    "AddressLine1" => $user->getInformationsFacturationUtilisateur()->getAdresse(),
                    "City" => $user->getInformationsFacturationUtilisateur()->getVille(),
                    "PostalCode" => $user->getInformationsFacturationUtilisateur()->getCodePostal(),
                    "Country" => $user->getInformationsFacturationUtilisateur()->getPays()
                ]
            ],
            "NextTransactionFees" => [
                "Currency" => "EUR",
                "Amount" => 0
            ]
        ];

        $response = $this->httpClient->request('POST', $apiUrl, [
            'headers' => [
                'Authorization' => "Bearer $accessToken",
                'Content-Type' => 'application/json',
            ],
            'json' => $body,
        ]);

        return $response->toArray();
    }

    public function editRecurringPayinRegistration(string $accessToken, string $recurringPayinRegistrationId, $authorId, $cardId, $user, $status): array
    {
        $apiUrl = $this->mangoPayApi->getConfig()->BaseUrl . "/v2.01/{$this->mangoPayApi->getConfig()->ClientId}/recurringpayinregistrations/{$recurringPayinRegistrationId}";

        $body = [
            "AuthorId" => $authorId,
            "CardId" => $cardId,
            "Status" => $status,
            "Billing" => [
                "FirstName" => "",
                "LastName" => $user->getInformationsFacturationUtilisateur()->getNomOuSociete(),
                "Address" => [
                    "AddressLine1" => $user->getInformationsFacturationUtilisateur()->getAdresse(),
                    "City" => $user->getInformationsFacturationUtilisateur()->getVille(),
                    "PostalCode" => $user->getInformationsFacturationUtilisateur()->getCodePostal(),
                    "Country" => $user->getInformationsFacturationUtilisateur()->getPays()
                ]
            ]
        ];

        $response = $this->httpClient->request('PUT', $apiUrl, [
            'headers' => [
                'Authorization' => "Bearer $accessToken",
                'Content-Type' => 'application/json',
            ],
            'json' => $body,
        ]);

        return $response->toArray();
    }

    public function viewRecurringRegistrationPayin(string $accessToken, string $recurringPayinRegistrationId): array
    {
        $apiUrl = $this->mangoPayApi->getConfig()->BaseUrl . "/v2.01/{$this->mangoPayApi->getConfig()->ClientId}/recurringpayinregistrations/{$recurringPayinRegistrationId}";

        $response = $this->httpClient->request('GET', $apiUrl, [
            'headers' => [
                'Authorization' => "Bearer $accessToken",
            ],
        ]);

        return $response->toArray();
    }

    public function createRecurringPayInCIT($accessToken, $tag, $debitedFunds, $fee, $statementDescriptor, $recurringPayinRegistrationId, $returnUrl, $ipAddress, $browserInfo): array
    {
        // Construct the request body
        $body = [
            "Tag" => $tag,
            "DebitedFunds" => [
                "Currency" => "EUR",
                "Amount" => $debitedFunds,
            ],
            "Fees" => [
                "Currency" => "EUR",
                "Amount" => $fee,
            ],
            "Culture" => "EN",
            "StatementDescriptor" => $statementDescriptor,
            "BrowserInfo" => [
                "AcceptHeader" => $browserInfo['AcceptHeader'],
                "JavaEnabled" => $browserInfo['JavaEnabled'],
                "Language" => $browserInfo['Language'],
                "ColorDepth" => $browserInfo['ColorDepth'],
                "ScreenHeight" => $browserInfo['ScreenHeight'],
                "ScreenWidth" => $browserInfo['ScreenWidth'],
                "TimeZoneOffset" => $browserInfo['TimeZoneOffset'],
                "UserAgent" => $browserInfo['UserAgent'],
                "JavascriptEnabled" => $browserInfo['JavascriptEnabled'],
            ],
            "IpAddress" => $ipAddress,
            "RecurringPayinRegistrationId" => $recurringPayinRegistrationId,
            "PreferredCardNetwork" => "MASTERCARD",
            "SecureModeNeeded" => true,
            "SecureMode" => "DEFAULT",
            "SecureModeReturnURL" => $returnUrl,
            'Requested3DSVersion' => 'V2_1',
            'Applied3DSVersion' => 'V2_1'
        ];

        // Make the HTTP request
        $apiUrl = $this->mangoPayApi->getConfig()->BaseUrl . "/v2.01/{$this->mangoPayApi->getConfig()->ClientId}/payins/recurring/card/direct";

        $response = $this->httpClient->request('POST', $apiUrl, [
            'headers' => [
                'Authorization' => "Bearer $accessToken",
                'Content-Type' => 'application/json',
            ],
            'json' => $body,
        ]);

        return $response->toArray();
    }

    public function createRecurringPayInMit($amount, $fee, $statementDescriptor, $tag, $recurringPayinRegistrationId)
    {
        $mit = new \MangoPay\RecurringPayInMIT();

        $mit->RecurringPayinRegistrationId = $recurringPayinRegistrationId;
        $mit->StatementDescriptor = $statementDescriptor;
        $mit->Tag = $tag;

        // Required if the registration’s NextTransactionDebitedFunds is empty.
        $mit->DebitedFunds = new \MangoPay\Money();
        $mit->DebitedFunds->Amount = $amount;
        $mit->DebitedFunds->Currency = 'EUR';
        $mit->Fees = new \MangoPay\Money();
        $mit->Fees->Amount = $fee;
        $mit->Fees->Currency = 'EUR';

        return $this->mangoPayApi->PayIns->CreateRecurringPayInRegistrationMIT($mit);
    }

    public function createStripeAccountToken($type, $data)
    {
        $dobDay = null;
        $dobMonth = null;
        $dobYear = null;

        if ($data['birthday']) {
            $birthDate = explode('/', $data['birthday']); // Format: 'YYYY-MM-DD'
            $dobDay = $birthDate[1];
            $dobMonth = $birthDate[0];
            $dobYear = $birthDate[2];
        }

        // Prepare data for account token
        $postData = [];

        if ($type === 'ROLE_ABONNE') {
            $postData = [
                'account' => [
                    'business_type' => 'individual',
                    'tos_shown_and_accepted' => true,
                    'individual' => [
                        'first_name' => $this->sanitizeData($data['firstname'] ?? null),
                        'last_name' => $this->sanitizeData($data['lastname'] ?? null),
                        'dob' => ['day' => $dobDay, 'month' => $dobMonth, 'year' => $dobYear],
                        'email' => $this->sanitizeData($data['user_email'] ?? null),
                        'phone' => $this->sanitizeData($data['phone']),
                        'gender' => $this->sanitizeData($data['sexe']),
                        'address' => [
                            'line1' => $this->sanitizeData($data['user_address_1'] ?? null),
                            'city' => $this->sanitizeData($data['user_city'] ?? null),
                            'state' => $this->sanitizeData($data['region_domicile'] ?? null),
                            'postal_code' => $this->sanitizeData($data['user_postcode'] ?? null),
                            'country' => $this->sanitizeData($data['user_country'] ?? null),
                        ]
                    ]
                ]
            ];
        } else {
            $postData = [
                'account' => [
                    'business_type' => 'company',
                    'tos_shown_and_accepted' => true,
                    'company' => [
                        'name' => $this->sanitizeData($data['firstname'] ?? null),
                        'tax_id' => '',
                        'phone' => $this->sanitizeData($data['phone']),
                        'address' => [
                            'line1' => $this->sanitizeData($data['user_address_1'] ?? null),
                            'city' => $this->sanitizeData($data['user_city'] ?? null),
                            'state' => $this->sanitizeData($data['region_domicile'] ?? null),
                            'postal_code' => $this->sanitizeData($data['user_postcode'] ?? null),
                            'country' => $this->sanitizeData($data['user_country'] ?? null),
                        ]
                    ]
                ]
            ];
        }

        $url = 'https://api.stripe.com/v1/tokens';

        return $this->executeCurlRequest($url, $postData);
    }

    public function createStripeUserFromToken($tokenId)
    {
        $url = "https://api.stripe.com/v1/accounts";

        $postData = [
            'controller' => [
                'stripe_dashboard' => ['type' => 'none'],
                'fees' => ['payer' => 'application'],
                'losses' => ['payments' => 'application'],
                'requirement_collection' => 'application'
            ],
            'account_token' => $tokenId,
            'capabilities' => [
                'transfers' => ['requested' => true],
            ],
        ];

        return $this->executeCurlRequest($url, $postData);
    }


    public function updateStripeUser($userId, $type, $data)
    {
        $curl = curl_init();
        // Prepare the base URL and API key#
        $url = "https://api.stripe.com/v1/accounts/$userId";
        $postData = [];
        $dobDay = null;
        $dobMonth = null;
        $dobYear = null;
        if ($type == 'ROLE_ABONNE') {
            // Update a Stripe Customer

            if ($data['birthday']) {
                $birthDate = explode('/', $data['birthday']); // Format: 'YYYY-MM-DD'
                $dobDay = $birthDate[1];
                $dobMonth = $birthDate[0];
                $dobYear = $birthDate[2];
            }
            $postData = [
                'email' => $this->sanitizeData($data['user_email']),
                'individual' => [
                    'first_name' => $this->sanitizeData($data['firstname'] ?? null),
                    'last_name' => $this->sanitizeData($data['lastname'] ?? null),
                    'dob' => ['day' => $dobDay, 'month' => $dobMonth, 'year' => $dobYear],
                    'email' => $this->sanitizeData($data['user_email'] ?? null),
                    'phone' => $this->sanitizeData($data['phone']),
                    'gender' => $this->sanitizeData($data['sexe']),
                    'address' => [
                        'line1' => $this->sanitizeData($data['user_address_1'] ?? null),
                        'city' => $this->sanitizeData($data['user_city'] ?? null),
                        'state' => $this->sanitizeData($data['region_domicile'] ?? null),
                        'postal_code' => $this->sanitizeData($data['user_postcode'] ?? null),
                        'country' => $this->sanitizeData($data['user_country'] ?? null),
                    ]
                ]
            ];
        } elseif ($type == 'ROLE_SOCIETE' || $type == 'ROLE_AUTO_ENTREPRENEUR') {
            // Update a Stripe Account
            $postData['email'] = $this->sanitizeData($data['billing_email'] ?? null);
            $postData['metadata'] = [
                'tva' => $this->sanitizeData($data['tva'] ?? null),
            ];
            $postData['business_profile'] = [
                'name' => $this->sanitizeData($data['nom_commercial'] ?? null),
                'support_phone' => $this->sanitizeData($data['billing_phone'] ?? null),
                'support_email' => $this->sanitizeData($data['billing_email'] ?? null),
            ];

            $postData['company'] = [
                'tax_id' => $this->sanitizeData($data['siret'] ?? null), // SIRET as Tax ID
                'vat_id' => $this->sanitizeData($data['tva'] ?? null),
                'name' => $this->sanitizeData($data['billing_company'] ?? null),
                'address' => [
                    'line1' => $this->sanitizeData($data['billing_address_1'] ?? null),
                    'city' => $this->sanitizeData($data['billing_city'] ?? null),
                    'state' => $this->sanitizeData($data['billing_state'] ?? null),
                    'postal_code' => $this->sanitizeData($data['billing_postcode'] ?? null),
                    'country' => $this->sanitizeData($data['billing_country'] ?? null),
                ]
            ];
        }
        return $this->executeCurlRequest($url, $postData);
    }

    public function createStripeAccountTokenForUpdate($type, $data)
    {
        $dobDay = null;
        $dobMonth = null;
        $dobYear = null;

        if (!empty($data['birthday'])) {
            $birthDate = explode('/', $data['birthday']); // Format: 'YYYY-MM-DD'
            $dobDay = $birthDate[1];
            $dobMonth = $birthDate[0];
            $dobYear = $birthDate[2];
        }

        // Prepare data for account token
        $postData = ['account' => ['business_type' => ($type === 'ROLE_ABONNE') ? 'individual' : 'company']];

        if ($type === 'ROLE_ABONNE') {
            $postData['account']['tos_shown_and_accepted'] = true;
            $postData['account']['individual'] = [
                'first_name' => $this->sanitizeData($data['firstname'] ?? null),
                'last_name' => $this->sanitizeData($data['lastname'] ?? null),
                'dob' => ['day' => $dobDay, 'month' => $dobMonth, 'year' => $dobYear],
                'email' => $this->sanitizeData($data['user_email'] ?? null),
                'phone' => $this->sanitizeData($data['phone']),
                'gender' => $this->sanitizeData($data['sexe']),
                'address' => [
                    'line1' => $this->sanitizeData($data['user_address_1'] ?? null),
                    'city' => $this->sanitizeData($data['user_city'] ?? null),
                    'state' => $this->sanitizeData($data['region_domicile'] ?? null),
                    'postal_code' => $this->sanitizeData($data['user_postcode'] ?? null),
                    'country' => $this->sanitizeData($data['user_country'] ?? null),
                ]
            ];
        } else {
            $postData['account'] += [
                'company' => [
                    'tax_id' => $this->sanitizeData($data['siret'] ?? null), // SIRET as Tax ID
                    'vat_id' => $this->sanitizeData($data['tva'] ?? null),
                    'name' => $this->sanitizeData($data['billing_company'] ?? null),
                    'phone' => $this->sanitizeData($data['billing_phone'] ?? null),
                    'address' => [
                        'line1' => $this->sanitizeData($data['billing_address_1'] ?? null),
                        'city' => $this->sanitizeData($data['billing_city'] ?? null),
                        'state' => $this->sanitizeData($data['billing_state'] ?? null),
                        'postal_code' => $this->sanitizeData($data['billing_postcode'] ?? null),
                        'country' => $this->sanitizeData($data['billing_country'] ?? null),
                    ]
                ]
            ];
        }

        $url = 'https://api.stripe.com/v1/tokens';

        return $this->executeCurlRequest($url, $postData);
    }

    public function updateStripeBusinessProfile($accountId, $data)
    {
        $url = "https://api.stripe.com/v1/accounts/$accountId";

        $postData = [
            'business_profile' => [
                'name' => $this->sanitizeData($data['nom_commercial'] ?? null),
                'support_phone' => $this->sanitizeData($data['billing_phone'] ?? null),
                'support_email' => $this->sanitizeData($data['billing_email'] ?? null),
            ]
        ];

        return $this->executeCurlRequest($url, $postData, 'POST');
    }




    public function updateStripeUserFromToken($accountId, $tokenId)
    {
        $url = "https://api.stripe.com/v1/accounts/$accountId";

        $postData = [
            'account_token' => $tokenId
        ];

        return $this->executeCurlRequest($url, $postData, 'POST');
    }


    public function createStripePersonToken($data)
    {
        $url = "https://api.stripe.com/v1/tokens";
        $dobYear = null;
        $dobMonth = null;
        $dobDay = null;
        // Split the birthday into day, month, year
        if ($data['birthday']) {
            $birthDate = explode('/', $data['birthday']); // Format: 'YYYY-MM-DD'
            $dobYear = $birthDate[2];
            $dobMonth = $birthDate[0];
            $dobDay = $birthDate[1];
        }

        $postData = [
            'person' => [
                'first_name' => $data['firstname'],
                'last_name' => $data['lastname'],
                'email' => $data['user_email'],
                'phone' => $data['phone'],
                'dob' => [
                    'day' => $dobDay,
                    'month' => $dobMonth,
                    'year' => $dobYear,
                ],
                'address' => [
                    'line1' => $data['user_address_1'],
                    'city' => $data['user_city'],
                    'state' => $data['region_domicile'],
                    'postal_code' => $data['user_postcode'],
                    'country' => $data['user_country'],
                ],
                'relationship' => [
                    'title' => 'Gerant',
                    'representative' => true
                ]
            ]
        ];

        return $this->executeCurlRequest($url, $postData);
    }

    public function createStripePersonFromToken($accountId, $personToken)
    {
        $url = "https://api.stripe.com/v1/accounts/$accountId/persons";

        $postData = [
            'person_token' => $personToken
        ];

        return $this->executeCurlRequest($url, $postData);
    }

    public function updateStripePersonFromToken($accountId, $personId, $personToken)
    {
        $url = "https://api.stripe.com/v1/accounts/$accountId/persons/$personId";

        $postData = [
            'person_token' => $personToken
        ];

        return $this->executeCurlRequest($url, $postData, 'POST');
    }


    /**
     * @param $accountId
     * @param $firstName
     * @param $lastName
     * @param $birthday
     * @param $email
     * @param $CountryOfResidence
     * @param null $addressLine1
     * @param null $city
     * @param null $region
     * @param null $postalCode
     * @return mixed
     */
    public function createStripePerson(
        $accountId,
        $firstName,
        $lastName,
        $birthday,
        $CountryOfResidence,
        $email,
        $addressLine1 = null,
        $city = null,
        $region = null,
        $postalCode = null
    )
    {
        $curl = curl_init();

        // Prepare the base URL and API key
        $apiKey = $this->apiKeys; // Assume this contains your Stripe secret key
        $url = "https://api.stripe.com/v1/accounts/$accountId/persons";
        $dobYear = null;
        $dobMonth = null;
        $dobDay = null;
        // Split the birthday into day, month, year
        if ($birthday) {
            $birthDate = explode('/', $birthday); // Format: 'YYYY-MM-DD'
            $dobYear = $birthDate[2];
            $dobMonth = $birthDate[0];
            $dobDay = $birthDate[1];
        }

        // Prepare data for the cURL request
        $data = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'dob' => [
                'day' => $dobDay,
                'month' => $dobMonth,
                'year' => $dobYear,
            ],
            'address' => [
                'line1' => $addressLine1,
                'city' => $city,
                'state' => $region,
                'postal_code' => $postalCode,
                'country' => $CountryOfResidence,
            ],
            'relationship' => [
                'title' => 'Gerant',
                'representative' => true
            ],
        ];
        return $this->executeCurlRequest($url, $data);
    }

    public function updateStripePerson($accountId, $personId, $data)
    {
        $curl = curl_init();
        $dobYear = null;
        $dobMonth = null;
        $dobDay = null;
        // Split the birthday into day, month, year
        if ($data['birthday']) {
            $birthDate = explode('/', $data['birthday']); // Format: 'YYYY-MM-DD'
            $dobYear = $birthDate[2];
            $dobMonth = $birthDate[0];
            $dobDay = $birthDate[1];
        }
        // Prepare the API key and the endpoint URL
        $apiKey = $this->apiKeys; // Replace with your actual Stripe secret key
        $url = "https://api.stripe.com/v1/accounts/$accountId/persons/$personId";

        // Prepare the data for the API request
        $postData = [
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'dob' => [
                'day' => $dobDay ?? null,
                'month' => $dobMonth ?? null,
                'year' => $dobYear ?? null,
            ],
            'metadata' => [
                'gender' => $data['sexe']
            ],
            'address' => [
                'line1' => $data['user_address_1'] ?? null,
                'country' => $data['user_country'] ?? null,
                'city' => $data['user_city'] ?? null,
                'state' => $data['region_domicile'] ?? null,
                'postal_code' => $data['user_postcode'] ?? null,
            ],
        ];

        // Remove null values from the data
        $postData = array_filter($postData, function ($value) {
            return $value !== null;
        });

        return $this->executeCurlRequest($url, $postData);
    }

    public function createStripeExternalAccount(
        $accountId,
        $number,
        $bic,
        $data
    )
    {
        $curl = curl_init();

        // Prepare the base URL and API key
        $apiKey = $this->apiKeys; // Assume this contains your Stripe secret key
        $url = "https://api.stripe.com/v1/accounts/$accountId/external_accounts";
        // Prepare data for the cURL request
        $data = [
            'default_for_currency' => true,
            'external_account' => [
                'object' => 'bank_account',
                'account_number' => $number,
                'country' => $data['vendor_account_country'],
                'currency' => 'EUR', // Required for connected accounts
                'object' => 'bank_account',
                'account_holder_name' => $data['vendor_account_name'],
                'account_holder_type' => 'company',
                'metadata' => [
                    'bic' => $bic
                ]
            ]
        ];
        return $this->executeCurlRequest($url, $data);
    }

    public function updateStripeExternalAccount(
        $accountId,
        $cardId,
        $number,
        $bic,
        $data
    )
    {
        $curl = curl_init();

        // Prepare the base URL and API key
        $apiKey = $this->apiKeys; // Assume this contains your Stripe secret key
        $url = "https://api.stripe.com/v1/accounts/$accountId/external_accounts/$cardId";
        // Prepare data for the cURL request
        $data = [
            'default_for_currency' => true
        ];

        return $this->executeCurlRequest($url, $data, 'POST');
    }

    public function getStripeCard($accountId, $cardId)
    {
        $url = "https://api.stripe.com/v1/accounts/$accountId/external_accounts/$cardId";
        return $this->executeCurlRequest($url, [], 'GET');
    }

    public function listAllExternalAccounts($accountId)
    {
        $url = "https://api.stripe.com/v1/accounts/$accountId/external_accounts";
        return $this->executeCurlRequest($url, [], 'GET');
    }

    /*
     * Delete Stripe Person Account
     */
    public function deleteStripePerson($accountId, $personId)
    {
        try {
            return $this->executeCurlRequest("https://api.stripe.com/v1/accounts/$accountId/persons/$personId", [], 'DELETE');
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }


    /*
     * Delete Stripe user Account
     */
    public function deleteStripeUser($accountId)
    {
        try {
            return $this->executeCurlRequest("https://api.stripe.com/v1/accounts/$accountId", [], 'DELETE');
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function updateStripeUserTosAcceptance($userId)
    {
        // Prepare the base URL
        $url = "https://api.stripe.com/v1/accounts/$userId";
        // Get current timestamp in seconds (required by Stripe)
        $currentTimestamp = time();
        // Get the user's IP address
        $userIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        // Prepare the data payload
        $postData['tos_acceptance'] = [
            'date' => $currentTimestamp, // Stripe requires UNIX timestamp (seconds, not milliseconds)
            'ip' => $userIp
        ];
        return $this->executeCurlRequest($url, $postData, 'POST');
    }

    /**
     * @param $url
     * @param $data
     * @param string $method
     * @return mixed
     */
    private function executeCurlRequest($url, $data, $method = 'POST')
    {
        $curl = curl_init();

        // Convert boolean values to "true" or "false" strings to comply with Stripe's API
        array_walk_recursive($data, function (&$value) {
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
        });

        // Convert data to application/x-www-form-urlencoded format
        $postData = http_build_query($data);

        // Set up the cURL options
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_USERPWD => $this->apiKeys . ':', // API key for authentication
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded' // Use the correct content type
            ]
        ]);

        // Execute the cURL request
        $response = curl_exec($curl);

        // Handle potential errors
        if (curl_errno($curl)) {
            $errorMessage = curl_error($curl);
            curl_close($curl);
            throw new Exception('cURL Error: ' . $errorMessage);
        }

        // Close the cURL session
        curl_close($curl);

        return json_decode($response, true);
    }

    function sanitizeData($value)
    {
        return $value === '' ? null : $value;
    }

}
