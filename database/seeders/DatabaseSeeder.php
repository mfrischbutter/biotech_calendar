<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Setting;
use App\Models\Status;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $company = Company::create([
            'name' => 'Schädlingsbekämpfung Hoffmann GmbH',
            'street' => 'Industriestraße 12',
            'zip' => '80339',
            'city' => 'München',
            'phone' => '+49 89 123456',
            'email' => 'info@hoffmann-sb.de',
        ]);

        // Calendar settings
        Setting::create(['company_id' => $company->id, 'key' => 'show_weekends', 'value' => 'false']);
        Setting::create(['company_id' => $company->id, 'key' => 'start_hour', 'value' => '7']);
        Setting::create(['company_id' => $company->id, 'key' => 'end_hour', 'value' => '18']);

        // --- Statuses (pest control themed) ---
        $statuses = [];
        $statusData = [
            ['name' => 'Erstbegehung', 'color' => '#f97316'],
            ['name' => 'Routinekontrolle', 'color' => '#3b82f6'],
            ['name' => 'Akutbehandlung', 'color' => '#ef4444'],
            ['name' => 'Nachkontrolle', 'color' => '#a855f7'],
            ['name' => 'Beratung', 'color' => '#ec4899'],
            ['name' => 'Dokumentation', 'color' => '#6b7280'],
            ['name' => 'Abgeschlossen', 'color' => '#22c55e'],
        ];

        foreach ($statusData as $i => $t) {
            $statuses[$t['name']] = Status::create([
                'company_id' => $company->id,
                'name' => $t['name'],
                'color' => $t['color'],
                'sort_order' => $i,
            ]);
        }

        // --- Users ---
        $owner = User::create([
            'first_name' => 'Stefan',
            'last_name' => 'Hoffmann',
            'email' => 'owner@biotech.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
            'company_id' => $company->id,
        ]);
        $owner->forceFill(['email_verified_at' => now()])->save();

        $markus = User::create([
            'first_name' => 'Markus',
            'last_name' => 'Weber',
            'email' => 'markus@hoffmann-sb.de',
            'password' => bcrypt('password'),
            'role' => 'employee',
            'company_id' => $company->id,
        ]);
        $markus->forceFill(['email_verified_at' => now()])->save();

        $lisa = User::create([
            'first_name' => 'Lisa',
            'last_name' => 'Bauer',
            'email' => 'lisa@hoffmann-sb.de',
            'password' => bcrypt('password'),
            'role' => 'employee',
            'company_id' => $company->id,
        ]);
        $lisa->forceFill(['email_verified_at' => now()])->save();

        $jonas = User::create([
            'first_name' => 'Jonas',
            'last_name' => 'Fischer',
            'email' => 'jonas@hoffmann-sb.de',
            'password' => bcrypt('password'),
            'role' => 'employee',
            'company_id' => $company->id,
        ]);
        $jonas->forceFill(['email_verified_at' => now()])->save();

        // Permissions for employees
        $fullPerms = array_keys(Permission::ALL);
        foreach ($fullPerms as $perm) {
            Permission::create(['company_id' => $company->id, 'user_id' => $markus->id, 'permission' => $perm]);
        }

        $limitedPerms = ['dashboard.view', 'appointments.view', 'appointments.create', 'appointments.edit', 'clients.view'];
        foreach ($limitedPerms as $perm) {
            Permission::create(['company_id' => $company->id, 'user_id' => $lisa->id, 'permission' => $perm]);
            Permission::create(['company_id' => $company->id, 'user_id' => $jonas->id, 'permission' => $perm]);
        }

        // --- Clients (realistic German pest control clients) ---
        $clients = [];

        $clientData = [
            ['Herr', 'Klaus', 'Bergmann', 'Bäckerei Bergmann', null, 'Sendlinger Str. 45', '80331', 'München', '+49 89 234567', 'bergmann@baeckerei-bergmann.de'],
            ['Frau', 'Sabine', 'Gruber', 'Hotel Residenz am Park', null, 'Leopoldstr. 78', '80802', 'München', '+49 89 345678', 'gruber@residenz-park.de'],
            ['Herr', 'Wolfgang', 'Mayer', null, null, 'Blumenstr. 3', '80331', 'München', '+49 89 456789', 'w.mayer@gmail.com'],
            ['Frau', 'Petra', 'Schuster', 'Gaststätte Zum Hirsch', null, 'Rosenheimer Str. 112', '81669', 'München', '+49 89 567890', 'schuster@zum-hirsch.de'],
            ['Herr', 'Dieter', 'Neumann', 'Lagerhalle Neumann KG', 'Neumann Logistik KG', 'Am Moosfeld 5', '81829', 'München', '+49 89 678901', 'neumann@neumann-logistik.de'],
            ['Frau', 'Martina', 'Krause', 'Kindergarten Sonnenschein e.V.', null, 'Goethestr. 22', '80336', 'München', '+49 89 789012', 'krause@kiga-sonnenschein.de'],
            ['Herr', 'Ralf', 'Zimmermann', null, null, 'Dachauer Str. 190', '80637', 'München', '+49 89 890123', 'r.zimmermann@web.de'],
            ['Frau', 'Claudia', 'Huber', 'Metzgerei Huber', null, 'Schleißheimer Str. 44', '80333', 'München', '+49 89 901234', 'c.huber@metzgerei-huber.de'],
            ['Herr', 'Jürgen', 'Lehmann', 'Wohnanlage Lehmann & Söhne', 'Lehmann & Söhne Hausverwaltung', 'Isartalstr. 8', '80469', 'München', '+49 89 112233', 'lehmann@lehmann-soehne.de'],
            ['Frau', 'Andrea', 'Koch', 'Restaurant Bella Vista', null, 'Maximilianstr. 15', '80539', 'München', '+49 89 223344', 'koch@bella-vista.de'],
            ['Herr', 'Hans-Peter', 'Braun', 'Supermarkt Braun', null, 'Landsberger Str. 88', '80339', 'München', '+49 89 334455', 'braun@supermarkt-braun.de'],
            ['Frau', 'Ingrid', 'Wagner', null, null, 'Nymphenburger Str. 51', '80636', 'München', '+49 89 445566', null],
        ];

        foreach ($clientData as $c) {
            $clients[] = Client::create([
                'company_id' => $company->id,
                'user_id' => $owner->id,
                'salutation' => $c[0],
                'first_name' => $c[1],
                'last_name' => $c[2],
                'company_name' => $c[3],
                'billing_name' => $c[4],
                'street' => $c[5],
                'zip' => $c[6],
                'city' => $c[7],
                'phone' => $c[8],
                'email' => $c[9],
            ]);
        }

        // Helper references
        $bergmann = $clients[0];
        $gruber = $clients[1];
        $mayer = $clients[2];
        $schuster = $clients[3];
        $neumann = $clients[4];
        $krause = $clients[5];
        $zimmermann = $clients[6];
        $huber = $clients[7];
        $lehmann = $clients[8];
        $koch = $clients[9];
        $braun = $clients[10];
        $wagner = $clients[11];

        // --- Helper to create appointments ---
        $today = Carbon::today();
        $monday = $today->copy()->startOfWeek(Carbon::MONDAY);

        $appt = function (
            string $title,
            Carbon $start,
            Carbon $end,
            ?Client $client,
            ?User $employee,
            ?Status $status,
            ?string $notes = null,
            ?array $checklist = null,
            ?User $createdBy = null,
        ) use ($company, $owner) {
            return Appointment::create([
                'company_id' => $company->id,
                'title' => $title,
                'start_at' => $start,
                'end_at' => $end,
                'client_id' => $client?->id,
                'employee_id' => $employee?->id,
                'status_id' => $status?->id,
                'notes' => $notes,
                'checklist' => $checklist,
                'created_by' => ($createdBy ?? $owner)->id,
            ]);
        };

        // =============================================
        // WEEK 1 (current week)
        // =============================================

        $mon = $monday;
        $tue = $monday->copy()->addDay();
        $wed = $monday->copy()->addDays(2);
        $thu = $monday->copy()->addDays(3);
        $fri = $monday->copy()->addDays(4);

        // --- Monday ---

        // Stefan: morning inspection + afternoon documentation
        $appt(
            'Erstbegehung Bäckerei Bergmann',
            $mon->copy()->setTime(8, 0),
            $mon->copy()->setTime(9, 30),
            $bergmann, $owner, $statuses['Erstbegehung'],
            'Mehlmottenbefall im Lagerraum gemeldet. Komplette Begehung aller Räumlichkeiten.',
            [
                ['text' => 'Lagerraum inspizieren', 'checked' => false],
                ['text' => 'Backstube prüfen', 'checked' => false],
                ['text' => 'Verkaufsraum kontrollieren', 'checked' => false],
                ['text' => 'Befallsbericht erstellen', 'checked' => false],
            ],
        );

        $appt(
            'Dokumentation KW-Berichte',
            $mon->copy()->setTime(14, 0),
            $mon->copy()->setTime(16, 0),
            null, $owner, $statuses['Dokumentation'],
            'Wochenberichte der letzten KW zusammenfassen und an Kunden versenden.',
        );

        // Markus: full day with overlapping appointments
        $appt(
            'Routinekontrolle Hotel Residenz',
            $mon->copy()->setTime(8, 0),
            $mon->copy()->setTime(10, 0),
            $gruber, $markus, $statuses['Routinekontrolle'],
            'Monatliche Kontrollrunde: Küche, Keller, Dachboden. Köderstationen prüfen.',
            [
                ['text' => 'Köderstationen Küche', 'checked' => false],
                ['text' => 'Köderstationen Keller', 'checked' => false],
                ['text' => 'Pheromonfallen Dachboden', 'checked' => false],
                ['text' => 'Protokoll unterschreiben lassen', 'checked' => false],
            ],
        );

        // Overlapping with hotel — Markus double-booked (realistic scenario)
        $appt(
            'Akutbehandlung Mayer — Wespen',
            $mon->copy()->setTime(9, 0),
            $mon->copy()->setTime(10, 30),
            $mayer, $markus, $statuses['Akutbehandlung'],
            'Dringend! Wespennest am Balkon, Kleinkinder im Haushalt.',
        );

        $appt(
            'Nachkontrolle Gaststätte Hirsch',
            $mon->copy()->setTime(13, 0),
            $mon->copy()->setTime(14, 0),
            $schuster, $markus, $statuses['Nachkontrolle'],
            'Kontrolle 2 Wochen nach Schabenbehandlung.',
        );

        // Lisa: morning block
        $appt(
            'Beratung Kindergarten Sonnenschein',
            $mon->copy()->setTime(9, 0),
            $mon->copy()->setTime(10, 30),
            $krause, $lisa, $statuses['Beratung'],
            'Beratungsgespräch zu präventiven Maßnahmen. Elternbrief vorbereiten.',
        );

        $appt(
            'Routinekontrolle Metzgerei Huber',
            $mon->copy()->setTime(11, 0),
            $mon->copy()->setTime(12, 0),
            $huber, $lisa, $statuses['Routinekontrolle'],
        );

        // Jonas: one appointment
        $appt(
            'Erstbegehung Lagerhalle Neumann',
            $mon->copy()->setTime(10, 0),
            $mon->copy()->setTime(12, 0),
            $neumann, $jonas, $statuses['Erstbegehung'],
            'Verdacht auf Rattenbefall im Außenbereich. Große Lagerhalle mit 3 Eingängen.',
            [
                ['text' => 'Außenbereich abgehen', 'checked' => false],
                ['text' => 'Laderampe inspizieren', 'checked' => false],
                ['text' => 'Fraßspuren dokumentieren', 'checked' => false],
                ['text' => 'Köderplan erstellen', 'checked' => false],
            ],
        );

        // UNASSIGNED Monday
        $appt(
            'Rückruf Frau Wagner — Ameisenbefall',
            $mon->copy()->setTime(9, 0),
            $mon->copy()->setTime(9, 30),
            $wagner, null, null,
            'Kundin hat angerufen, Ameisenstraße in der Küche. Termin vereinbaren.',
        );

        // --- Tuesday ---

        $appt(
            'Rattenbekämpfung Lagerhalle Neumann',
            $tue->copy()->setTime(7, 30),
            $tue->copy()->setTime(10, 0),
            $neumann, $jonas, $statuses['Akutbehandlung'],
            'Köderboxen auslegen, Zugänge abdichten.',
            [
                ['text' => 'Köderboxen auslegen (12 Stk.)', 'checked' => false],
                ['text' => 'Eingänge abdichten', 'checked' => false],
                ['text' => 'Dokumentation HACCP', 'checked' => false],
            ],
        );

        $appt(
            'Routinekontrolle Restaurant Bella Vista',
            $tue->copy()->setTime(8, 0),
            $tue->copy()->setTime(9, 30),
            $koch, $markus, $statuses['Routinekontrolle'],
            'Quartalskontrolle Küche und Lager.',
        );

        // Stefan + Lisa: overlapping team meeting
        $appt(
            'Teammeeting Wochenplanung',
            $tue->copy()->setTime(8, 30),
            $tue->copy()->setTime(9, 30),
            null, $owner, $statuses['Dokumentation'],
        );

        $appt(
            'Teammeeting Wochenplanung',
            $tue->copy()->setTime(8, 30),
            $tue->copy()->setTime(9, 30),
            null, $lisa, $statuses['Dokumentation'],
        );

        $appt(
            'Beratung Wohnanlage Lehmann',
            $tue->copy()->setTime(10, 0),
            $tue->copy()->setTime(11, 30),
            $lehmann, $owner, $statuses['Beratung'],
            'Taubenabwehr für Innenhof besprechen. Herr Lehmann will Angebot für Netze.',
        );

        // Three overlapping for Lisa on Tuesday afternoon
        $appt(
            'Nachkontrolle Supermarkt Braun',
            $tue->copy()->setTime(13, 0),
            $tue->copy()->setTime(14, 0),
            $braun, $lisa, $statuses['Nachkontrolle'],
        );

        $appt(
            'Kundenbesuch Zimmermann — Marder',
            $tue->copy()->setTime(13, 30),
            $tue->copy()->setTime(15, 0),
            $zimmermann, $lisa, $statuses['Erstbegehung'],
            'Mardergeräusche auf dem Dachboden. Erstbegehung und Beratung.',
        );

        $appt(
            'Dokumentation Bella Vista',
            $tue->copy()->setTime(14, 0),
            $tue->copy()->setTime(15, 0),
            $koch, $lisa, $statuses['Dokumentation'],
        );

        // UNASSIGNED Tuesday
        $appt(
            'Angebotsanfrage Schädlingsmonitoring',
            $tue->copy()->setTime(11, 0),
            $tue->copy()->setTime(12, 0),
            null, null, $statuses['Beratung'],
            'Neue Anfrage über Website. Großküche in Pasing, monatliches Monitoring gewünscht.',
        );

        // --- Wednesday ---

        $appt(
            'Wespenentfernung Fam. Mayer',
            $wed->copy()->setTime(8, 0),
            $wed->copy()->setTime(9, 0),
            $mayer, $markus, $statuses['Akutbehandlung'],
            'Wespennest entfernen. Schutzausrüstung mitnehmen!',
        );

        $appt(
            'Routinekontrolle Bäckerei Bergmann',
            $wed->copy()->setTime(9, 30),
            $wed->copy()->setTime(11, 0),
            $bergmann, $markus, $statuses['Routinekontrolle'],
        );

        $appt(
            'Akutbehandlung Schaben — Gaststätte Hirsch',
            $wed->copy()->setTime(7, 0),
            $wed->copy()->setTime(9, 0),
            $schuster, $jonas, $statuses['Akutbehandlung'],
            'Schabenbefall in der Küche, vor Öffnungszeit behandeln!',
        );

        $appt(
            'Beratung Hotel Residenz — Bettwanzen',
            $wed->copy()->setTime(10, 0),
            $wed->copy()->setTime(11, 30),
            $gruber, $owner, $statuses['Beratung'],
            'Gast hat Bettwanzen gemeldet. Zimmer 204 + angrenzende Zimmer inspizieren.',
            [
                ['text' => 'Zimmer 204 inspizieren', 'checked' => false],
                ['text' => 'Zimmer 203 + 205 prüfen', 'checked' => false],
                ['text' => 'Behandlungsplan erstellen', 'checked' => false],
                ['text' => 'Kostenvoranschlag', 'checked' => false],
            ],
        );

        $appt(
            'Schulung Hygienevorschriften',
            $wed->copy()->setTime(14, 0),
            $wed->copy()->setTime(16, 0),
            null, $owner, $statuses['Beratung'],
            'Interne Schulung für das Team.',
        );

        $appt(
            'Schulung Hygienevorschriften',
            $wed->copy()->setTime(14, 0),
            $wed->copy()->setTime(16, 0),
            null, $markus, $statuses['Beratung'],
        );

        $appt(
            'Schulung Hygienevorschriften',
            $wed->copy()->setTime(14, 0),
            $wed->copy()->setTime(16, 0),
            null, $lisa, $statuses['Beratung'],
        );

        $appt(
            'Schulung Hygienevorschriften',
            $wed->copy()->setTime(14, 0),
            $wed->copy()->setTime(16, 0),
            null, $jonas, $statuses['Beratung'],
        );

        // Lisa: short morning appointments
        $appt(
            'Nachkontrolle Kindergarten Sonnenschein',
            $wed->copy()->setTime(8, 0),
            $wed->copy()->setTime(9, 0),
            $krause, $lisa, $statuses['Nachkontrolle'],
        );

        $appt(
            'Ködertausch Metzgerei Huber',
            $wed->copy()->setTime(9, 30),
            $wed->copy()->setTime(10, 30),
            $huber, $lisa, $statuses['Routinekontrolle'],
        );

        // --- Thursday ---

        // Early morning parallel appointments
        $appt(
            'Bettwanzenbehandlung Hotel Residenz',
            $thu->copy()->setTime(7, 0),
            $thu->copy()->setTime(10, 0),
            $gruber, $markus, $statuses['Akutbehandlung'],
            'Thermische Behandlung Zimmer 204. Zugang ab 7 Uhr über Hintereingang.',
        );

        $appt(
            'Bettwanzenbehandlung Hotel Residenz',
            $thu->copy()->setTime(7, 0),
            $thu->copy()->setTime(10, 0),
            $gruber, $jonas, $statuses['Akutbehandlung'],
            'Unterstützung Markus. Zimmer 203 + 205 behandeln.',
        );

        $appt(
            'Routinekontrolle Supermarkt Braun',
            $thu->copy()->setTime(8, 0),
            $thu->copy()->setTime(9, 30),
            $braun, $lisa, $statuses['Routinekontrolle'],
        );

        $appt(
            'Kundengespräch Lehmann — Angebot',
            $thu->copy()->setTime(10, 0),
            $thu->copy()->setTime(11, 0),
            $lehmann, $owner, $statuses['Beratung'],
            'Angebot Taubennetze besprechen und unterzeichnen.',
        );

        // Afternoon: multiple short appointments
        $appt(
            'Nachkontrolle Lagerhalle Neumann',
            $thu->copy()->setTime(13, 0),
            $thu->copy()->setTime(14, 0),
            $neumann, $jonas, $statuses['Nachkontrolle'],
            'Köderverbrauch prüfen, ggf. nachfüllen.',
        );

        $appt(
            'Erstbegehung Frau Wagner',
            $thu->copy()->setTime(14, 0),
            $thu->copy()->setTime(15, 30),
            $wagner, $lisa, $statuses['Erstbegehung'],
            'Ameisenbefall Küche und Terrasse.',
        );

        // No-status appointment (appointment with no status)
        $appt(
            'Materialbestellung + Lager aufräumen',
            $thu->copy()->setTime(15, 0),
            $thu->copy()->setTime(17, 0),
            null, $owner, null,
            'Ködermaterial nachbestellen, Lager sortieren.',
        );

        // UNASSIGNED Thursday
        $appt(
            'Neukunde Anfrage — Taubenproblem',
            $thu->copy()->setTime(10, 0),
            $thu->copy()->setTime(10, 30),
            null, null, null,
            'Anruf von Hausverwaltung Schwabing. Muss noch zugewiesen werden.',
        );

        // --- Friday ---

        $appt(
            'Abschlusskontrolle Gaststätte Hirsch',
            $fri->copy()->setTime(8, 0),
            $fri->copy()->setTime(9, 0),
            $schuster, $jonas, $statuses['Abgeschlossen'],
            'Schabenbehandlung erfolgreich. Abschlussdokumentation.',
        );

        // Recurring appointment (weekly routine)
        $recurringParent = $appt(
            'Wöchentliche Routinekontrolle Bella Vista',
            $fri->copy()->setTime(9, 0),
            $fri->copy()->setTime(10, 0),
            $koch, $markus, $statuses['Routinekontrolle'],
        );
        $recurringParent->update([
            'recurrence_type' => 'weekly',
            'recurrence_end' => $monday->copy()->addWeeks(8)->format('Y-m-d'),
            'parent_id' => null,
        ]);

        $appt(
            'Dokumentation + Wochenbericht',
            $fri->copy()->setTime(10, 0),
            $fri->copy()->setTime(12, 0),
            null, $owner, $statuses['Dokumentation'],
            'KW-Bericht zusammenstellen, Fotos sortieren.',
        );

        $appt(
            'Ameisenbehandlung Frau Wagner',
            $fri->copy()->setTime(10, 0),
            $fri->copy()->setTime(11, 30),
            $wagner, $lisa, $statuses['Akutbehandlung'],
            'Gel-Köder und Barrierespray ausbringen.',
        );

        // Overlapping Friday afternoon for Markus
        $appt(
            'Nachkontrolle Mayer — Wespen',
            $fri->copy()->setTime(13, 0),
            $fri->copy()->setTime(14, 0),
            $mayer, $markus, $statuses['Nachkontrolle'],
        );

        $appt(
            'Routinekontrolle Kindergarten Sonnenschein',
            $fri->copy()->setTime(13, 30),
            $fri->copy()->setTime(14, 30),
            $krause, $markus, $statuses['Routinekontrolle'],
        );

        // No-client appointment
        $appt(
            'Fahrzeuginspektion Servicebus',
            $fri->copy()->setTime(15, 0),
            $fri->copy()->setTime(16, 0),
            null, $jonas, null,
        );

        // UNASSIGNED Friday
        $appt(
            'Dringende Anfrage — Rattenbefall Keller',
            $fri->copy()->setTime(8, 0),
            $fri->copy()->setTime(8, 30),
            null, null, $statuses['Akutbehandlung'],
            'Anruf von Privatperson aus Laim. Sofort zuweisen!',
        );

        // =============================================
        // WEEK 2 (next week)
        // =============================================

        $mon2 = $monday->copy()->addWeek();
        $tue2 = $mon2->copy()->addDay();
        $wed2 = $mon2->copy()->addDays(2);
        $thu2 = $mon2->copy()->addDays(3);
        $fri2 = $mon2->copy()->addDays(4);

        // --- Monday W2 ---

        $appt(
            'Nachkontrolle Bäckerei Bergmann',
            $mon2->copy()->setTime(8, 0),
            $mon2->copy()->setTime(9, 30),
            $bergmann, $owner, $statuses['Nachkontrolle'],
            'Mehlmottenfallen kontrollieren. 2 Wochen nach Erstbegehung.',
        );

        $appt(
            'Routinekontrolle Hotel Residenz',
            $mon2->copy()->setTime(8, 0),
            $mon2->copy()->setTime(10, 0),
            $gruber, $markus, $statuses['Routinekontrolle'],
        );

        $appt(
            'Taubenabwehr Installation Lehmann',
            $mon2->copy()->setTime(8, 0),
            $mon2->copy()->setTime(12, 0),
            $lehmann, $jonas, $statuses['Akutbehandlung'],
            'Taubennetze im Innenhof montieren. Material ist im Lager.',
            [
                ['text' => 'Material laden', 'checked' => false],
                ['text' => 'Netze Innenhof Nord', 'checked' => false],
                ['text' => 'Netze Innenhof Süd', 'checked' => false],
                ['text' => 'Spikes Fenstersims', 'checked' => false],
                ['text' => 'Abnahme mit Hr. Lehmann', 'checked' => false],
            ],
        );

        $appt(
            'Nachkontrolle Frau Wagner — Ameisen',
            $mon2->copy()->setTime(10, 0),
            $mon2->copy()->setTime(11, 0),
            $wagner, $lisa, $statuses['Nachkontrolle'],
        );

        $appt(
            'Beratung Supermarkt Braun — Monitoring',
            $mon2->copy()->setTime(11, 0),
            $mon2->copy()->setTime(12, 0),
            $braun, $lisa, $statuses['Beratung'],
            'Neues Monitoringsystem besprechen.',
        );

        // Overlapping for owner
        $appt(
            'Beratung Neukunde Taubenproblem',
            $mon2->copy()->setTime(10, 0),
            $mon2->copy()->setTime(11, 0),
            null, $owner, $statuses['Beratung'],
            'Erstgespräch mit Hausverwaltung aus Schwabing.',
        );

        // --- Tuesday W2 ---

        $appt(
            'Teammeeting Wochenplanung',
            $tue2->copy()->setTime(8, 30),
            $tue2->copy()->setTime(9, 30),
            null, $owner, $statuses['Dokumentation'],
        );

        $appt(
            'Akutbehandlung Ratten — Bella Vista Keller',
            $tue2->copy()->setTime(7, 0),
            $tue2->copy()->setTime(9, 0),
            $koch, $markus, $statuses['Akutbehandlung'],
            'Rattenspuren im Keller entdeckt. Vor Öffnungszeit behandeln.',
        );

        $appt(
            'Erstbegehung Neukunde Schwabing',
            $tue2->copy()->setTime(10, 0),
            $tue2->copy()->setTime(11, 30),
            null, $owner, $statuses['Erstbegehung'],
            'Taubenbefall Dachgeschoss Wohnanlage.',
        );

        $appt(
            'Routinekontrolle Gaststätte Hirsch',
            $tue2->copy()->setTime(10, 0),
            $tue2->copy()->setTime(11, 0),
            $schuster, $jonas, $statuses['Routinekontrolle'],
        );

        $appt(
            'Ködertausch Lagerhalle Neumann',
            $tue2->copy()->setTime(13, 0),
            $tue2->copy()->setTime(14, 30),
            $neumann, $jonas, $statuses['Routinekontrolle'],
        );

        $appt(
            'Nachkontrolle Zimmermann — Marder',
            $tue2->copy()->setTime(14, 0),
            $tue2->copy()->setTime(15, 0),
            $zimmermann, $lisa, $statuses['Nachkontrolle'],
            'Lebendfalle kontrollieren.',
        );

        // UNASSIGNED
        $appt(
            'Reklamation Metzgerei Huber',
            $tue2->copy()->setTime(9, 0),
            $tue2->copy()->setTime(9, 30),
            $huber, null, $statuses['Routinekontrolle'],
            'Frau Huber meldet erneuten Mottenbefall trotz Behandlung. Bitte schnell zuweisen.',
        );

        // --- Wednesday W2 ---

        $appt(
            'Bettwanzen-Nachkontrolle Hotel Residenz',
            $wed2->copy()->setTime(8, 0),
            $wed2->copy()->setTime(9, 30),
            $gruber, $markus, $statuses['Nachkontrolle'],
            'Kontrollinspektion 1 Woche nach thermischer Behandlung.',
        );

        $appt(
            'Routinekontrolle Bäckerei Bergmann',
            $wed2->copy()->setTime(10, 0),
            $wed2->copy()->setTime(11, 0),
            $bergmann, $markus, $statuses['Routinekontrolle'],
        );

        // Long appointment for Jonas
        $appt(
            'Taubenabwehr Fertigstellung Lehmann',
            $wed2->copy()->setTime(8, 0),
            $wed2->copy()->setTime(13, 0),
            $lehmann, $jonas, $statuses['Akutbehandlung'],
            'Restarbeiten Taubennetze + Reinigung.',
        );

        $appt(
            'Ameisenbekämpfung Wagner — Abschluss',
            $wed2->copy()->setTime(9, 0),
            $wed2->copy()->setTime(10, 0),
            $wagner, $lisa, $statuses['Abgeschlossen'],
            'Letzte Kontrolle, Behandlung abgeschlossen.',
        );

        $appt(
            'Kundenbesuch Kindergarten Sonnenschein',
            $wed2->copy()->setTime(10, 30),
            $wed2->copy()->setTime(11, 30),
            $krause, $lisa, $statuses['Routinekontrolle'],
        );

        $appt(
            'Angebotserstellung Monitoringvertrag',
            $wed2->copy()->setTime(14, 0),
            $wed2->copy()->setTime(16, 0),
            null, $owner, $statuses['Dokumentation'],
            'Monitoringvertrag für Supermarkt Braun finalisieren.',
        );

        // --- Thursday W2 ---

        // Triple overlap for Markus
        $appt(
            'Nachkontrolle Bella Vista Keller',
            $thu2->copy()->setTime(8, 0),
            $thu2->copy()->setTime(9, 0),
            $koch, $markus, $statuses['Nachkontrolle'],
        );

        $appt(
            'Routinekontrolle Metzgerei Huber',
            $thu2->copy()->setTime(8, 30),
            $thu2->copy()->setTime(10, 0),
            $huber, $markus, $statuses['Routinekontrolle'],
            'Inkl. Reklamationsbearbeitung Mottenbefall.',
        );

        $appt(
            'Ködertausch Mayer',
            $thu2->copy()->setTime(9, 0),
            $thu2->copy()->setTime(9, 30),
            $mayer, $markus, $statuses['Routinekontrolle'],
        );

        $appt(
            'Routinekontrolle Wohnanlage Lehmann',
            $thu2->copy()->setTime(10, 0),
            $thu2->copy()->setTime(11, 30),
            $lehmann, $owner, $statuses['Nachkontrolle'],
            'Abnahme Taubenabwehr + erste Kontrollrunde.',
        );

        $appt(
            'Nachkontrolle Lagerhalle Neumann',
            $thu2->copy()->setTime(10, 0),
            $thu2->copy()->setTime(11, 0),
            $neumann, $jonas, $statuses['Nachkontrolle'],
        );

        $appt(
            'Erstbegehung neues Objekt Pasing',
            $thu2->copy()->setTime(13, 0),
            $thu2->copy()->setTime(15, 0),
            null, $lisa, $statuses['Erstbegehung'],
            'Großküche in Pasing. Angebot Website-Anfrage.',
        );

        // Short no-status appointment
        $appt(
            'Telefonat Versicherung',
            $thu2->copy()->setTime(16, 0),
            $thu2->copy()->setTime(16, 30),
            null, $owner, null,
        );

        // --- Friday W2 ---

        // Recurring child
        $recurringChild = $appt(
            'Wöchentliche Routinekontrolle Bella Vista',
            $fri2->copy()->setTime(9, 0),
            $fri2->copy()->setTime(10, 0),
            $koch, $markus, $statuses['Routinekontrolle'],
        );
        $recurringChild->update(['parent_id' => $recurringParent->id]);

        $appt(
            'Dokumentation + Wochenbericht',
            $fri2->copy()->setTime(10, 0),
            $fri2->copy()->setTime(12, 0),
            null, $owner, $statuses['Dokumentation'],
        );

        $appt(
            'Abschlusskontrolle Zimmermann — Marder',
            $fri2->copy()->setTime(8, 0),
            $fri2->copy()->setTime(9, 30),
            $zimmermann, $lisa, $statuses['Abgeschlossen'],
            'Marder gefangen und umgesiedelt. Eingang abgedichtet.',
        );

        $appt(
            'Nachkontrolle Hotel Residenz — Bettwanzen',
            $fri2->copy()->setTime(8, 0),
            $fri2->copy()->setTime(9, 0),
            $gruber, $jonas, $statuses['Nachkontrolle'],
        );

        $appt(
            'Routinekontrolle Kindergarten Sonnenschein',
            $fri2->copy()->setTime(13, 0),
            $fri2->copy()->setTime(14, 0),
            $krause, $markus, $statuses['Routinekontrolle'],
        );

        $appt(
            'Beratung Supermarkt Braun — Vertragsabschluss',
            $fri2->copy()->setTime(14, 0),
            $fri2->copy()->setTime(15, 0),
            $braun, $owner, $statuses['Beratung'],
            'Monitoringvertrag unterzeichnen.',
        );

        // UNASSIGNED Friday W2
        $appt(
            'Neue Anfrage — Kakerlaken Gastro Haidhausen',
            $fri2->copy()->setTime(10, 0),
            $fri2->copy()->setTime(10, 30),
            null, null, $statuses['Akutbehandlung'],
            'Dringend! Gesundheitsamt hat Frist gesetzt. Sofort zuweisen.',
        );
    }
}
