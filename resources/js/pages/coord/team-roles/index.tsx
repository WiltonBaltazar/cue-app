import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { CheckboxField, TextField } from '@/components/volunteer-form/fields';
import { index, store, update } from '@/routes/coord/team-roles';

type TeamRoleRow = {
    id: number;
    name: string;
    description: string | null;
    isActive: boolean;
    volunteerCount: number;
};

function RoleEditor({
    role,
    onDone,
}: {
    role: TeamRoleRow;
    onDone: () => void;
}) {
    const form = useForm({
        name: role.name,
        description: role.description ?? '',
        is_active: role.isActive,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(update(role.id).url, {
            preserveScroll: true,
            onSuccess: onDone,
        });
    };

    return (
        <form onSubmit={submit} className="space-y-3 p-4">
            <TextField
                label="Nome"
                value={form.data.name}
                onChange={(e) => form.setData('name', e.target.value)}
                error={form.errors.name}
            />
            <TextField
                label="Descrição"
                optional
                value={form.data.description}
                onChange={(e) => form.setData('description', e.target.value)}
                error={form.errors.description}
            />
            <CheckboxField
                label="Activa"
                hint="Funções inactivas não aparecem em novos cadastros nem em novas posições."
                checked={form.data.is_active}
                onChange={(checked) => form.setData('is_active', checked)}
            />
            <div className="flex gap-2">
                <Button
                    type="submit"
                    className="min-h-11"
                    disabled={form.processing}
                >
                    {form.processing && <Spinner />}
                    Guardar
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    className="min-h-11"
                    onClick={onDone}
                >
                    Cancelar
                </Button>
            </div>
        </form>
    );
}

/**
 * Catalogue of team roles (FR-007). Roles are deactivated, never deleted.
 */
export default function TeamRolesIndex({
    teamRoles,
}: {
    teamRoles: TeamRoleRow[];
}) {
    const [editing, setEditing] = useState<number | null>(null);
    const form = useForm({ name: '', description: '' });

    const add = (event: FormEvent) => {
        event.preventDefault();
        form.post(store().url, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <>
            <Head title="Funções" />

            <div className="space-y-6 p-4 lg:max-w-3xl">
                <div>
                    <h1 className="text-2xl font-semibold">Funções</h1>
                    <p className="text-muted-foreground">
                        Áreas de serviço usadas no cadastro e nas posições dos
                        eventos.
                    </p>
                </div>

                <ul className="divide-y rounded-lg border">
                    {teamRoles.map((role) => (
                        <li key={role.id}>
                            {editing === role.id ? (
                                <RoleEditor
                                    role={role}
                                    onDone={() => setEditing(null)}
                                />
                            ) : (
                                <div className="flex min-h-11 items-center gap-3 p-4">
                                    <span className="min-w-0 flex-1">
                                        <span className="block font-medium">
                                            {role.name}
                                        </span>
                                        <span className="text-sm text-muted-foreground">
                                            {role.volunteerCount}{' '}
                                            {role.volunteerCount === 1
                                                ? 'voluntário'
                                                : 'voluntários'}
                                            {role.description
                                                ? ` · ${role.description}`
                                                : ''}
                                        </span>
                                    </span>
                                    {!role.isActive && (
                                        <Badge variant="secondary">
                                            Inactiva
                                        </Badge>
                                    )}
                                    <Button
                                        variant="outline"
                                        className="min-h-11"
                                        onClick={() => setEditing(role.id)}
                                    >
                                        Editar
                                    </Button>
                                </div>
                            )}
                        </li>
                    ))}
                </ul>

                <form
                    onSubmit={add}
                    className="space-y-3 rounded-lg border p-4"
                >
                    <h2 className="font-semibold">Nova função</h2>
                    <TextField
                        label="Nome"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        error={form.errors.name}
                    />
                    <TextField
                        label="Descrição"
                        optional
                        value={form.data.description}
                        onChange={(e) =>
                            form.setData('description', e.target.value)
                        }
                        error={form.errors.description}
                    />
                    <Button
                        type="submit"
                        className="min-h-11"
                        disabled={form.processing || !form.data.name.trim()}
                    >
                        Adicionar
                    </Button>
                </form>
            </div>
        </>
    );
}

TeamRolesIndex.layout = {
    breadcrumbs: [{ title: 'Funções', href: index() }],
};
