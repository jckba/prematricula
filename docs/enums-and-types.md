# Enums, Estados y Tipos TypeScript

Esta guía proporciona la referencia de tipos y valores permitidos para facilitar la integración en clientes web / móviles (React, Vue, Angular, Next.js, etc.).

---

## 1. Enums del Backend

### `UserRole` (Rol de Usuario)
```typescript
export enum UserRole {
  ESTUDIANTE = 'ESTUDIANTE',
  COORDINADOR = 'COORDINADOR',
}
```

### `PreEnrollmentStatus` (Estado de la Prematrícula)
```typescript
export enum PreEnrollmentStatus {
  BORRADOR = 'BORRADOR',    // En edición por el estudiante
  ENVIADA = 'ENVIADA',      // Registro exitoso; no requiere aprobación
  APROBADA = 'APROBADA',    // Solo lectura de historial anterior
  RECHAZADA = 'RECHAZADA',  // Solo lectura de historial anterior
}
```

### `SchedulePreference` (Preferencia de Turno)
```typescript
export enum SchedulePreference {
  MANANA = 'MANANA',
  TARDE = 'TARDE',
  SIN_PREFERENCIA = 'SIN_PREFERENCIA',
}
```

### `AcademicPeriodStatus` (Estado del Periodo Académico)
```typescript
export enum AcademicPeriodStatus {
  PLANIFICACION = 'PLANIFICACION',
  PREMATRICULA = 'PREMATRICULA',
  EN_CURSO = 'EN_CURSO',
  CERRADO = 'CERRADO',
}
```

### `RecordStatus` (Estado de Registro)
```typescript
export enum RecordStatus {
  ACTIVO = 'ACTIVO',
  INACTIVO = 'INACTIVO',
}
```

### `AcademicHistoryStatus` (Estado en Historial Académico)
```typescript
export enum AcademicHistoryStatus {
  APROBADO = 'APROBADO',
  DESAPROBADO = 'DESAPROBADO',
  EN_CURSO = 'EN_CURSO',
  RETIRADO = 'RETIRADO',
}
```

---

## 2. Definiciones TypeScript para el Frontend

Puedes copiar estas interfaces en tu proyecto frontend (ej. `src/types/api.ts`):

```typescript
// --- Autenticación ---

export interface AuthUser {
  id: number;
  correo_institucional: string;
  rol: UserRole;
  estado: RecordStatus;
  cargo: 'DIRECTOR_ESCUELA' | 'SECRETARIA' | null;
}

export interface LoginResponse {
  message: string;
  data: {
    token: string;
    expires_at: string;
    user: AuthUser;
  };
}

export interface MeResponse {
  data: AuthUser;
}

// --- Periodo y Cursos ---

export interface AcademicPeriodSummary {
  id: number;
  codigo: string;
}

export interface CoursePrerequisite {
  id: number;
  codigo: string;
  nombre: string;
}

export interface Course {
  id: number;
  codigo: string;
  nombre: string;
  creditos: number;
  ciclo: number;
  horas_teoria: string;       // Decimal con dos posiciones; horas académicas semanales
  horas_practica: string;
  horas_laboratorio: string;
  estado: RecordStatus;
  prerrequisitos?: CoursePrerequisite[];
}

export interface AvailableCoursesResponse {
  data: {
    periodo: AcademicPeriodSummary;
    creditos_maximos: number;
    cursos: Course[];
  };
}

// --- Prematrícula ---

export interface StudentSummary {
  id: number;
  codigo_universitario: string;
  nombres: string;
  apellidos: string;
}

export interface ReviewerSummary {
  id: number;
  correo_institucional: string;
}

export interface PreEnrollmentDetail {
  id: number;
  curso: Course;
  preferencia_turno: SchedulePreference;
}

export interface PreEnrollment {
  id: number;
  estado: PreEnrollmentStatus;
  correccion_en_progreso: boolean;
  current_revision_id: number | null;
  revision_vigente?: {
    id: number;
    revision_number: number | null;
    window_id: number | null;
    submitted_at: string | null;
    receipt_code: string | null;
    receipt_available: boolean;
    schema_version: number;
    snapshot: Record<string, unknown>;
  } | null;
  validacion_academica?: {
    estado: 'PENDIENTE_RESULTADOS' | 'VALIDA' | 'REQUIERE_AJUSTE';
    evaluated_at: string;
    cursos: Array<{
      course_id: number;
      codigo: string;
      estado: 'PENDIENTE_RESULTADOS' | 'VALIDA' | 'REQUIERE_AJUSTE';
      motivo: string | null;
      prerrequisitos: Array<{
        id: number;
        codigo: string;
        estado_historial: AcademicHistoryStatus | null;
        estado_validacion: 'PENDIENTE_RESULTADOS' | 'VALIDA' | 'REQUIERE_AJUSTE';
      }>;
    }>;
  } | null;
  fecha_envio: string | null;     // ISO 8601 string
  fecha_revision: string | null;  // ISO 8601 string
  comentarios_coordinador: string | null;
  periodo: AcademicPeriodSummary;
  estudiante: StudentSummary;
  detalles: PreEnrollmentDetail[];
  creditos_totales: number;
  revisado_por: ReviewerSummary | null;
}

export interface PreEnrollmentResponse {
  data: PreEnrollment;
}

// --- Paginación ---

export interface PaginationLinks {
  first: string;
  last: string;
  prev: string | null;
  next: string | null;
}

export interface PaginationMeta {
  current_page: number;
  from: number | null;
  last_page: number;
  per_page: number;
  to: number | null;
  total: number;
}

export interface PaginatedResponse<T> {
  data: T[];
  links: PaginationLinks;
  meta: PaginationMeta;
}

// --- Payloads de Petición ---

export interface AddCoursePayload {
  course_id: number;
  preferencia_turno?: SchedulePreference;
}

export interface UpdatePreferencePayload {
  preferencia_turno: SchedulePreference;
}

export interface RejectPreEnrollmentPayload {
  comentario: string;
}
```
