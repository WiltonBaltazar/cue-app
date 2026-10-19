import type { ReactNode } from 'react';
import { formatDate, formatPhone } from '@/lib/format';
import type { VolunteerFull } from '@/types';

function Row({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="grid gap-1 py-2 sm:grid-cols-[12rem_1fr]">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="text-sm whitespace-pre-line">{children || '—'}</dd>
        </div>
    );
}

/**
 * Read-only full profile for coordinators (contains sensitive data; FR-026).
 */
export function VolunteerProfileDetails({
    volunteer,
}: {
    volunteer: VolunteerFull;
}) {
    return (
        <div className="space-y-6">
            <section aria-labelledby="identification">
                <h2 id="identification" className="font-semibold">
                    Identificação e contactos
                </h2>
                <dl className="divide-y">
                    <Row label="Nome">{volunteer.fullName}</Row>
                    <Row label="Alcunha">{volunteer.nickname}</Row>
                    <Row label="Data de nascimento">
                        {formatDate(volunteer.birthDate)} ({volunteer.age} anos)
                    </Row>
                    <Row label="Bairro">{volunteer.neighbourhood}</Row>
                    <Row label="Telefone">
                        {volunteer.phone
                            ? `${formatPhone(volunteer.phone)}${volunteer.phoneHasWhatsapp ? ' (WhatsApp)' : ''}`
                            : null}
                    </Row>
                    {volunteer.whatsappPhone && (
                        <Row label="WhatsApp">
                            {formatPhone(volunteer.whatsappPhone)}
                        </Row>
                    )}
                    <Row label="E-mail">{volunteer.email}</Row>
                    <Row label="Redes sociais">
                        {volunteer.socialMedia
                            .map(
                                (profile) =>
                                    `${profile.network}: ${profile.handle}`,
                            )
                            .join('\n')}
                    </Row>
                    {volunteer.isMinor && (
                        <>
                            <Row label="Encarregado">
                                {volunteer.guardianName}
                            </Row>
                            <Row label="Telefone do encarregado">
                                {volunteer.guardianPhone
                                    ? formatPhone(volunteer.guardianPhone)
                                    : null}
                            </Row>
                            <Row label="Consentimento do encarregado">
                                {volunteer.guardianConsent
                                    ? `Confirmado ${volunteer.guardianConsent.method?.toLowerCase() ?? ''} a ${formatDate(volunteer.guardianConsent.confirmedAt)}`
                                    : 'Ainda não confirmado'}
                            </Row>
                        </>
                    )}
                </dl>
            </section>

            <section aria-labelledby="education">
                <h2 id="education" className="font-semibold">
                    Escolaridade e formação
                </h2>
                <dl className="divide-y">
                    <Row label="Escolaridade">{volunteer.educationLevel}</Row>
                    <Row label="Formação">{volunteer.training}</Row>
                </dl>
            </section>

            <section aria-labelledby="experience">
                <h2 id="experience" className="font-semibold">
                    Áreas e experiência
                </h2>
                <dl className="divide-y">
                    <Row label="Outra área de interesse">
                        {volunteer.otherArea}
                    </Row>
                    <Row label="Tem experiência em">
                        {[
                            ...volunteer.experienceAreas,
                            volunteer.otherExperience,
                        ]
                            .filter(Boolean)
                            .join(', ') || 'Ainda sem experiência'}
                    </Row>
                    <Row label="Descrição da experiência">
                        {volunteer.experienceDescription}
                    </Row>
                    <Row label="Pode apoiar outros">
                        {volunteer.canSupportOthers ? 'Sim' : 'Não'}
                    </Row>
                </dl>
            </section>

            <section aria-labelledby="availability">
                <h2 id="availability" className="font-semibold">
                    Disponibilidade
                </h2>
                <dl className="divide-y">
                    <Row label="Dias e períodos">
                        {volunteer.availability
                            .map((slot) => slot.label)
                            .join('\n')}
                    </Row>
                    <Row label="Outros dias">
                        {volunteer.otherDaysByArrangement
                            ? 'Mediante combinação prévia'
                            : 'Não'}
                    </Row>
                    <Row label="Frequência">
                        {volunteer.frequencyNote
                            ? `${volunteer.maxFrequencyLabel} — ${volunteer.frequencyNote}`
                            : volunteer.maxFrequencyLabel}
                    </Row>
                    <Row label="Horários variáveis">
                        {volunteer.variableHours ? 'Sim' : 'Não'}
                    </Row>
                    <Row label="Reuniões e formações">
                        {volunteer.trainingAvailabilityLabel}
                    </Row>
                </dl>
            </section>

            <section aria-labelledby="motivations">
                <h2 id="motivations" className="font-semibold">
                    Compromissos, motivações e sugestões
                </h2>
                <dl className="divide-y">
                    <Row label="Compromissos">{volunteer.commitments}</Row>
                    <Row label="Motivações">{volunteer.motivations}</Row>
                    <Row label="Expectativas">{volunteer.expectations}</Row>
                    <Row label="Sonhos e objectivos">{volunteer.goals}</Row>
                    <Row label="Comentários">{volunteer.comments}</Row>
                    <Row label="Sugestões">{volunteer.suggestions}</Row>
                </dl>
            </section>
        </div>
    );
}
