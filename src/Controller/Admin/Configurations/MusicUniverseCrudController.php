<?php

namespace App\Controller\Admin\Configurations;

use App\Entity\MusicUniverse;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\String\Slugger\SluggerInterface;

class MusicUniverseCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly RequestStack $requestStack,
        private readonly SluggerInterface $slugger,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return MusicUniverse::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle('index', 'Univers musicaux')
            ->setPageTitle('detail', 'Details univers musical')
            ->setPageTitle('edit', 'Modifier univers musical')
            ->setEntityLabelInSingular('Univers musical')
            ->setEntityLabelInPlural('Univers musicaux')
            ->setDefaultSort(['position' => 'ASC', 'id' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('label', 'Libelle'),
            TextField::new('slug', 'Slug')->hideOnForm(),
            IntegerField::new('position', 'Position'),
            BooleanField::new('isActive', 'Actif'),
            DateTimeField::new('createdAt', 'Cree le')->onlyOnIndex(),
            DateTimeField::new('updatedAt', 'MAJ le')->onlyOnIndex(),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        $importCsv = Action::new('importCsv', 'Importer CSV')
            ->linkToUrl(function () {
                $request = $this->requestStack->getCurrentRequest();
                return $this->adminUrlGenerator
                    ->setAll($request?->query->all() ?? [])
                    ->setAction('importCsv')
                    ->generateUrl();
            })
            ->setIcon('fa fa-file-csv')
            ->setCssClass('btn btn-primary')
            ->createAsGlobalAction();

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $importCsv)
            ->setPermission(Action::NEW, 'ROLE_SUPER_ADMIN')
            ->setPermission(Action::DELETE, 'ROLE_SUPER_ADMIN')
            ->setPermission('importCsv', 'ROLE_SUPER_ADMIN');
    }

    public function importCsv(AdminContext $context, Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $file = $request->files->get('csv_file');

            if (!$file) {
                $this->addFlash('danger', 'Veuillez selectionner un fichier CSV.');
                return $this->redirectToRoute('admin', ['_locale' => $request->getLocale()]);
            }

            $path = $file->getRealPath();
            if (!$path || !is_readable($path)) {
                $this->addFlash('danger', 'Le fichier CSV est illisible.');
                return $this->redirectToRoute('admin', ['_locale' => $request->getLocale()]);
            }

            $created = 0;
            $updated = 0;
            $ignored = 0;
            $errors = 0;

            $handle = fopen($path, 'r');
            if ($handle === false) {
                $this->addFlash('danger', 'Impossible d\'ouvrir le fichier CSV.');
                return $this->redirectToRoute('admin', ['_locale' => $request->getLocale()]);
            }

            $firstLine = fgets($handle);
            if ($firstLine === false) {
                fclose($handle);
                $this->addFlash('warning', 'Le fichier CSV est vide.');
                return $this->redirectToRoute('admin', ['_locale' => $request->getLocale()]);
            }

            $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
            rewind($handle);

            $header = fgetcsv($handle, 0, $delimiter);
            if (!is_array($header)) {
                fclose($handle);
                $this->addFlash('danger', 'Entete CSV invalide.');
                return $this->redirectToRoute('admin', ['_locale' => $request->getLocale()]);
            }

            $header = array_map(static fn ($h) => strtolower(trim((string) $h)), $header);
            if (isset($header[0])) {
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]) ?? $header[0];
            }
            $isHeader = in_array('label', $header, true);
            if (!$isHeader) {
                rewind($handle);
            }

            $repository = $this->em->getRepository(MusicUniverse::class);
            $knownBySlug = [];
            foreach ($repository->findAll() as $existing) {
                $existingSlug = $existing->getSlug();
                if ($existingSlug) {
                    $knownBySlug[strtolower($existingSlug)] = $existing;
                }
            }

            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                if (!is_array($row) || count(array_filter($row, static fn ($v) => trim((string) $v) !== '')) === 0) {
                    continue;
                }

                try {
                    if ($isHeader) {
                        $map = [];
                        foreach ($header as $i => $name) {
                            $map[$name] = isset($row[$i]) ? trim((string) $row[$i]) : '';
                        }
                        $label = trim((string) ($map['label'] ?? ''));
                        $position = isset($map['position']) && $map['position'] !== '' ? (int) $map['position'] : 0;
                        $isActive = $this->toBool($map['is_active'] ?? '1');
                    } else {
                        $label = trim((string) ($row[0] ?? ''));
                        $position = isset($row[1]) && trim((string) $row[1]) !== '' ? (int) $row[1] : 0;
                        $isActive = $this->toBool($row[2] ?? '1');
                    }

                    if ($label === '') {
                        $ignored++;
                        continue;
                    }

                    $slug = strtolower((string) $this->slugger->slug($label));
                    $entity = $knownBySlug[$slug] ?? null;

                    if (!$entity) {
                        $entity = new MusicUniverse();
                        $entity->setLabel($label);
                        $entity->setPosition($position);
                        $entity->setIsActive($isActive);
                        $this->em->persist($entity);
                        $knownBySlug[$slug] = $entity;
                        $created++;
                    } else {
                        $entity->setLabel($label);
                        $entity->setPosition($position);
                        $entity->setIsActive($isActive);
                        $updated++;
                    }
                } catch (\Throwable $e) {
                    $errors++;
                }
            }
            fclose($handle);

            try {
                $this->em->flush();
            } catch (\Throwable $e) {
                $this->addFlash('danger', 'Import interrompu: ' . $e->getMessage());
                return $this->redirect(
                    $this->adminUrlGenerator
                        ->setController(self::class)
                        ->setAction(Action::INDEX)
                        ->generateUrl()
                );
            }

            $this->addFlash(
                'success',
                sprintf(
                    'Import termine. Crees: %d | MAJ: %d | Ignores: %d | Erreurs: %d',
                    $created,
                    $updated,
                    $ignored,
                    $errors
                )
            );

            return $this->redirect(
                $this->adminUrlGenerator
                    ->setController(self::class)
                    ->setAction(Action::INDEX)
                    ->generateUrl()
            );
        }

        return $this->render('admin/Configurations/MusicUniverse/import_csv.html.twig', [
            'index_url' => $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::INDEX)
                ->generateUrl(),
            'import_url' => $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction('importCsv')
                ->generateUrl(),
        ]);
    }

    private function toBool(string $value): bool
    {
        $v = strtolower(trim($value));
        return in_array($v, ['1', 'true', 'oui', 'yes', 'y'], true);
    }
}

