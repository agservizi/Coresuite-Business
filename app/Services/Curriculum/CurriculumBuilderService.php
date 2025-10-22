<?php
declare(strict_types=1);

namespace App\Services\Curriculum;

use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;

class CurriculumBuilderService
{
    private PDO $pdo;
    private string $storagePath;

    public function __construct(PDO $pdo, string $rootPath)
    {
        $this->pdo = $pdo;
        $this->storagePath = rtrim($rootPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'curriculum';
    }

    public function ensureStorageDirectory(): void
    {
        if (!is_dir($this->storagePath) && !mkdir($concurrentDirectory = $this->storagePath, 0775, true) && !is_dir($concurrentDirectory)) {
            throw new RuntimeException('Impossibile creare la directory per i curriculum.');
        }
    }

    public function buildEuropass(int $curriculumId): array
    {
        $curriculum = $this->loadCurriculum($curriculumId);
        $sections = $this->loadSections($curriculumId);

        $this->ensureStorageDirectory();

        $fileName = sprintf('cv_europass_%s.pdf', $curriculumId . '_' . (new DateTimeImmutable())->format('YmdHis'));
        $fullPath = $this->storagePath . DIRECTORY_SEPARATOR . $fileName;

        $pdf = $this->createPdfInstance();
    $pdf->SetMargins(18.0, 18.0, 18.0);
    $pdf->SetAutoPageBreak(true, 18.0);
        $pdf->AddPage();

        $this->renderHeader($pdf, $curriculum);
        $this->renderPersonalProfile($pdf, $curriculum);
        $this->renderExperienceSection($pdf, $sections['experiences']);
        $this->renderEducationSection($pdf, $sections['education']);
        $this->renderSkillsSection($pdf, $sections['skills']);
        $this->renderLanguageSection($pdf, $sections['languages']);
        $this->renderAdditionalInfo($pdf, $curriculum);

    $pdf->Output($fullPath, 'F');

        return [
            'relative_path' => 'assets/uploads/curriculum/' . $fileName,
            'full_path' => $fullPath,
            'generated_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];
    }

    private function loadCurriculum(int $curriculumId): array
    {
        $stmt = $this->pdo->prepare('SELECT cv.*, c.nome, c.cognome, c.email, c.telefono
            FROM curriculum cv
            LEFT JOIN clienti c ON c.id = cv.cliente_id
            WHERE cv.id = :id');
        $stmt->execute([':id' => $curriculumId]);
        $curriculum = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$curriculum) {
            throw new RuntimeException('Curriculum non trovato.');
        }

        return $curriculum;
    }

    /**
     * @return array{experiences:array<int,array<string,mixed>>,education:array<int,array<string,mixed>>,languages:array<int,array<string,mixed>>,skills:array<int,array<string,mixed>>}
     */
    private function loadSections(int $curriculumId): array
    {
        $sections = [
            'experiences' => $this->fetchSection('curriculum_experiences', $curriculumId),
            'education' => $this->fetchSection('curriculum_education', $curriculumId),
            'languages' => $this->fetchSection('curriculum_languages', $curriculumId),
            'skills' => $this->fetchSection('curriculum_skills', $curriculumId),
        ];

        return $sections;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function fetchSection(string $table, int $curriculumId): array
    {
        $column = $table === 'curriculum_languages' ? 'language' : 'ordering';
        $order = $table === 'curriculum_languages' ? 'ASC' : 'ASC';
        $stmt = $this->pdo->prepare("SELECT * FROM {$table} WHERE curriculum_id = :id ORDER BY {$column} {$order}");
        $stmt->execute([':id' => $curriculumId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function createPdfInstance()
    {
        $className = '\\Mpdf\\Mpdf';
        if (!class_exists($className)) {
            throw new RuntimeException('Libreria mPDF non disponibile.');
        }

        return new $className([
            'format' => 'A4',
            'margin_left' => 18,
            'margin_right' => 18,
            'margin_top' => 18,
            'margin_bottom' => 18,
        ]);
    }

    private function renderHeader($pdf, array $curriculum): void
    {
    $pdf->SetFont('DejaVu Sans', 'B', 18);
        $pdf->SetTextColor(0, 51, 102);
        $pdf->Cell(0, 12, 'Curriculum Vitae Europass', 0, 1, 'L');
    $pdf->SetFont('DejaVu Sans', '', 11);
        $pdf->SetTextColor(51, 51, 51);
        $pdf->Cell(0, 6, trim(($curriculum['nome'] ?? '') . ' ' . ($curriculum['cognome'] ?? '')), 0, 1, 'L');
        if (!empty($curriculum['email'])) {
            $pdf->Cell(0, 6, (string) $curriculum['email'], 0, 1, 'L');
        }
        if (!empty($curriculum['telefono'])) {
            $pdf->Cell(0, 6, (string) $curriculum['telefono'], 0, 1, 'L');
        }
        $pdf->Ln(4);
    }

    private function renderSectionTitle($pdf, string $title): void
    {
    $pdf->SetFont('DejaVu Sans', 'B', 12);
        $pdf->SetTextColor(0, 51, 102);
        $pdf->Cell(0, 8, $title, 0, 1, 'L');
        $pdf->SetDrawColor(0, 51, 102);
        $pdf->SetLineWidth(0.4);
        $pdf->Line($pdf->GetX(), $pdf->GetY(), $pdf->GetX() + 180, $pdf->GetY());
        $pdf->Ln(4);
    }

    private function renderPersonalProfile($pdf, array $curriculum): void
    {
        $this->renderSectionTitle($pdf, 'Profilo Personale');
    $pdf->SetFont('DejaVu Sans', '', 11);
        $pdf->SetTextColor(51, 51, 51);
        $profile = (string) ($curriculum['professional_summary'] ?? '');
        if ($profile === '') {
            $profile = 'Profilo non ancora compilato.';
        }
        $pdf->MultiCell(0, 6, $profile);
        $pdf->Ln(2);
    }

    private function renderExperienceSection($pdf, array $experiences): void
    {
        $this->renderSectionTitle($pdf, 'Esperienza Professionale');
    $pdf->SetFont('DejaVu Sans', '', 11);
        $pdf->SetTextColor(51, 51, 51);
        if (!$experiences) {
            $pdf->MultiCell(0, 6, 'Nessuna esperienza inserita.');
            $pdf->Ln(2);
            return;
        }
        foreach ($experiences as $experience) {
            $dateRange = $this->formatDateRange($experience['start_date'] ?? '', $experience['end_date'] ?? '', (bool) ($experience['is_current'] ?? 0));
            $pdf->SetFont('DejaVu Sans', 'B', 11);
            $pdf->Cell(0, 6, trim(($experience['role_title'] ?? '') . ' - ' . ($experience['employer'] ?? '')), 0, 1, 'L');
            $pdf->SetFont('DejaVu Sans', 'I', 10);
            $pdf->Cell(0, 5, $dateRange, 0, 1, 'L');
            $pdf->SetFont('DejaVu Sans', '', 10);
            if (!empty($experience['description'])) {
                $pdf->MultiCell(0, 5, (string) $experience['description']);
            }
            $pdf->Ln(2);
        }
    }

    private function renderEducationSection($pdf, array $education): void
    {
        $this->renderSectionTitle($pdf, 'Istruzione e Formazione');
    $pdf->SetFont('DejaVu Sans', '', 11);
        $pdf->SetTextColor(51, 51, 51);
        if (!$education) {
            $pdf->MultiCell(0, 6, 'Nessun percorso formativo inserito.');
            $pdf->Ln(2);
            return;
        }
        foreach ($education as $item) {
            $dateRange = $this->formatDateRange($item['start_date'] ?? '', $item['end_date'] ?? '', false);
            $pdf->SetFont('DejaVu Sans', 'B', 11);
            $pdf->Cell(0, 6, trim(($item['title'] ?? '') . ' - ' . ($item['institution'] ?? '')), 0, 1, 'L');
            $pdf->SetFont('DejaVu Sans', 'I', 10);
            $pdf->Cell(0, 5, $dateRange, 0, 1, 'L');
            $pdf->SetFont('DejaVu Sans', '', 10);
            if (!empty($item['description'])) {
                $pdf->MultiCell(0, 5, (string) $item['description']);
            }
            $pdf->Ln(2);
        }
    }

    private function renderSkillsSection($pdf, array $skills): void
    {
        $this->renderSectionTitle($pdf, 'Competenze');
    $pdf->SetFont('DejaVu Sans', '', 11);
        $pdf->SetTextColor(51, 51, 51);
        if (!$skills) {
            $pdf->MultiCell(0, 6, 'Nessuna competenza registrata.');
            $pdf->Ln(2);
            return;
        }
        foreach ($skills as $skill) {
            $line = trim(($skill['category'] ?? '') . ': ' . ($skill['skill'] ?? ''));
            if (!empty($skill['level'])) {
                $line .= ' (' . $skill['level'] . ')';
            }
            $pdf->SetFont('DejaVu Sans', 'B', 10);
            $pdf->Cell(0, 5, $line, 0, 1, 'L');
            $pdf->SetFont('DejaVu Sans', '', 10);
            if (!empty($skill['description'])) {
                $pdf->MultiCell(0, 5, (string) $skill['description']);
            }
            $pdf->Ln(2);
        }
    }

    private function renderLanguageSection($pdf, array $languages): void
    {
        $this->renderSectionTitle($pdf, 'Competenze Linguistiche');
    $pdf->SetFont('DejaVu Sans', '', 11);
        $pdf->SetTextColor(51, 51, 51);
        if (!$languages) {
            $pdf->MultiCell(0, 6, 'Competenze linguistiche non inserite.');
            $pdf->Ln(2);
            return;
        }
        foreach ($languages as $language) {
            $pdf->SetFont('DejaVu Sans', 'B', 10);
            $pdf->Cell(0, 6, (string) ($language['language'] ?? ''), 0, 1, 'L');
            $pdf->SetFont('DejaVu Sans', '', 10);
            $levels = array_filter([
                'Ascolto: ' . ($language['listening'] ?? ''),
                'Lettura: ' . ($language['reading'] ?? ''),
                'Interazione: ' . ($language['interaction'] ?? ''),
                'Produzione: ' . ($language['production'] ?? ''),
                'Scrittura: ' . ($language['writing'] ?? '')
            ], function ($item) {
                return !$this->stringEndsWith($item, ': ');
            });
            if ($levels) {
                $pdf->MultiCell(0, 5, implode(' | ', $levels));
            }
            if (!empty($language['certification'])) {
                $pdf->SetFont('DejaVu Sans', 'I', 9);
                $pdf->Cell(0, 5, 'Certificazione: ' . $language['certification'], 0, 1, 'L');
            }
            $pdf->Ln(2);
        }
    }

    private function renderAdditionalInfo($pdf, array $curriculum): void
    {
        $this->renderSectionTitle($pdf, 'Informazioni Addizionali');
    $pdf->SetFont('DejaVu Sans', '', 10);
        $text = [];
        if (!empty($curriculum['key_competences'])) {
            $text[] = 'Competenze chiave: ' . (string) $curriculum['key_competences'];
        }
        if (!empty($curriculum['digital_competences'])) {
            $text[] = 'Competenze digitali: ' . (string) $curriculum['digital_competences'];
        }
        if (!empty($curriculum['driving_license'])) {
            $text[] = 'Patente: ' . (string) $curriculum['driving_license'];
        }
        if (!empty($curriculum['additional_information'])) {
            $text[] = (string) $curriculum['additional_information'];
        }
        if (!$text) {
            $text[] = 'Nessuna informazione aggiuntiva disponibile.';
        }
        foreach ($text as $paragraph) {
            $pdf->MultiCell(0, 5, $paragraph);
            $pdf->Ln(1);
        }
    }

    private function formatDateRange(?string $start, ?string $end, bool $isCurrent): string
    {
        $startFormatted = $this->formatDate($start);
        $endFormatted = $isCurrent ? 'Presente' : $this->formatDate($end);
        if ($startFormatted === '' && $endFormatted === '') {
            return 'Periodo non specificato';
        }
        if ($startFormatted === '') {
            return 'Fino a ' . $endFormatted;
        }
        if ($endFormatted === '') {
            return 'Dal ' . $startFormatted;
        }
        return $startFormatted . ' - ' . $endFormatted;
    }

    private function formatDate(?string $dateValue): string
    {
        if (!$dateValue) {
            return '';
        }
        try {
            $date = new DateTimeImmutable($dateValue);
        } catch (Throwable) {
            return '';
        }
        return $date->format('m/Y');
    }

    private function stringEndsWith(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }
        $needleLength = strlen($needle);
        if ($needleLength > strlen($haystack)) {
            return false;
        }
        return substr($haystack, -$needleLength) === $needle;
    }
}
