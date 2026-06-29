import { Head, Link, usePage } from '@inertiajs/react';
import {
    Wrench,
    Camera,
    FileCheck,
    Languages,
    Link2,
    ShieldCheck,
    Workflow,
    LogIn,
    ArrowRight,
    Car,
    MessageSquare,
    CreditCard,
    CheckCircle2,
    Sparkles,
    Lock,
    Cloud,
    Clock,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { Button } from '@/Components/ui/button';
import { useInView } from '@/hooks/useInView';

interface RevealProps {
    children: React.ReactNode;
    delay?: number;
    className?: string;
}

function Reveal({ children, delay = 0, className }: RevealProps) {
    const [ref, inView] = useInView<HTMLDivElement>();
    return (
        <div
            ref={ref}
            className={cn('home-reveal', inView && 'is-in', className)}
            style={{ transitionDelay: `${delay}ms` }}
        >
            {children}
        </div>
    );
}

const FEATURES = [
    { icon: Camera, title: 'Photo & video evidence', body: 'Capture every stage. Stored in Google Cloud, timestamped, stage-locked.', accent: 'from-emerald-500/15 to-emerald-500/0' },
    { icon: FileCheck, title: 'Itemised estimates', body: 'Customers approve or decline each line item. Revisions versioned, never overwritten.', accent: 'from-sky-500/15 to-sky-500/0' },
    { icon: Languages, title: 'AI translation EN ↔ PL', body: 'Mechanics write in their language. Customers read in theirs. Automotive glossary preserved.', accent: 'from-indigo-500/15 to-indigo-500/0' },
    { icon: Link2, title: 'No-login portal', body: 'Customers get a signed link. Review media, ask questions, approve. No password to forget.', accent: 'from-violet-500/15 to-violet-500/0' },
    { icon: ShieldCheck, title: 'Immutable audit trail', body: 'Every approval, decline and scope change logged with actor, timestamp, payload.', accent: 'from-amber-500/15 to-amber-500/0' },
    { icon: Workflow, title: 'Connected to your CRM', body: 'Reads bookings and customer identity from inteteam_crm. Reuses your notification channels.', accent: 'from-rose-500/15 to-rose-500/0' },
];

const STEPS = [
    { icon: Car, title: 'Open the job', body: 'Pick a vehicle, assign mechanics, link the CRM booking.' },
    { icon: Camera, title: 'Document each stage', body: 'Snap photos as you go. Notes auto-translate for the customer.' },
    { icon: MessageSquare, title: 'Send the estimate', body: 'Customer reviews line by line. Questions land on the right photo.' },
    { icon: CreditCard, title: 'Handover & collect', body: 'Inspection checklist, optional online payment, signed handover.' },
];

const TRUST = ['inteteam_crm', 'Google Cloud Storage', 'Signed portal links', 'EN ↔ PL', 'Immutable audit log', 'Inte.Team SSO'];

export default function Home() {
    const { errors, ssoPublicUrl } = usePage<{ errors: Record<string, string>; ssoPublicUrl?: string }>().props;
    const ssoError = errors?.sso;
    const ssoLogoutUrl = ssoPublicUrl ? `${ssoPublicUrl}/logout` : null;

    return (
        <div className="min-h-screen bg-slate-50 text-slate-900 antialiased selection:bg-emerald-200/60">
            <Head title="InteTeam Garage — repair work, documented." />

            {ssoError && (
                <div className="border-b border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3">
                        <span>{ssoError}</span>
                        {ssoLogoutUrl && (
                            <a
                                href={ssoLogoutUrl}
                                className="rounded-md bg-amber-900 px-3 py-1.5 text-xs font-medium text-amber-50 hover:bg-amber-800"
                            >
                                Log out of SSO and try again
                            </a>
                        )}
                    </div>
                </div>
            )}

            <header className="sticky top-0 z-40 border-b border-slate-200/60 bg-white/75 backdrop-blur-xl">
                <div className="mx-auto flex h-14 max-w-6xl items-center justify-between px-4">
                    <Link href="/" className="flex items-center gap-2">
                        <span className="relative inline-flex h-7 w-7 items-center justify-center rounded-lg bg-slate-900 text-white">
                            <Wrench className="h-4 w-4" />
                            <span className="absolute -right-0.5 -top-0.5 inline-block h-2 w-2 rounded-full bg-emerald-400 home-pulse-ring" />
                        </span>
                        <span className="text-sm font-semibold tracking-tight">InteTeam Garage</span>
                    </Link>
                    <nav className="hidden items-center gap-6 text-xs font-medium text-slate-600 sm:flex">
                        <a href="#features" className="transition-colors hover:text-slate-900">Features</a>
                        <a href="#how-it-works" className="transition-colors hover:text-slate-900">How it works</a>
                        <a href="#portal" className="transition-colors hover:text-slate-900">For customers</a>
                    </nav>
                    <div className="flex items-center gap-2">
                        <Button asChild size="sm" className="bg-slate-900 hover:bg-slate-700">
                            <a href="/sign-in">
                                <LogIn className="h-4 w-4" />
                                Sign in
                            </a>
                        </Button>
                    </div>
                </div>
            </header>

            <section className="relative overflow-hidden">
                <div aria-hidden className="pointer-events-none absolute inset-0">
                    <div className="absolute inset-0 home-grid-bg" />
                    <div className="absolute -top-32 left-[10%] h-80 w-80 rounded-full bg-emerald-300/40 blur-3xl home-blob" />
                    <div className="absolute top-20 right-[5%] h-96 w-96 rounded-full bg-sky-300/40 blur-3xl home-blob" style={{ animationDelay: '-6s' }} />
                    <div className="absolute -bottom-20 left-[35%] h-72 w-72 rounded-full bg-violet-300/30 blur-3xl home-blob" style={{ animationDelay: '-12s' }} />
                </div>

                <div className="relative px-4 pb-20 pt-16 sm:pt-24">
                    <div className="mx-auto max-w-3xl text-center">
                        <div className="home-fade-in">
                            <span className="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white/80 px-3 py-1 text-xs font-medium text-slate-700 shadow-sm backdrop-blur">
                                <span className="relative inline-flex h-2 w-2">
                                    <span className="absolute inset-0 inline-flex rounded-full bg-emerald-400 home-pulse-ring" />
                                    <span className="relative inline-flex h-2 w-2 rounded-full bg-emerald-500" />
                                </span>
                                <span>For independent garages · live now</span>
                            </span>
                        </div>
                        <h1
                            className="mt-6 text-4xl font-semibold leading-[1.05] tracking-tight sm:text-6xl home-fade-up"
                            style={{ animationDelay: '0.1s' }}
                        >
                            Repair work,{' '}
                            <span className="home-gradient-text">documented.</span>
                        </h1>
                        <p
                            className="mx-auto mt-5 max-w-xl text-base leading-relaxed text-slate-600 sm:text-lg home-fade-up"
                            style={{ animationDelay: '0.25s' }}
                        >
                            Every photo, estimate and customer approval — timestamped, attributed, immutable.
                            End the disputes. Keep the cars moving.
                        </p>
                        <div
                            className="mt-8 flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:items-center home-fade-up"
                            style={{ animationDelay: '0.4s' }}
                        >
                            <Button asChild size="lg" className="group h-12 rounded-full bg-slate-900 px-6 text-sm shadow-lg shadow-slate-900/10 hover:bg-slate-800">
                                <a href="/sign-in">
                                    Sign in
                                    <ArrowRight className="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                                </a>
                            </Button>
                            <Button asChild size="lg" variant="outline" className="h-12 rounded-full border-slate-300 bg-white/80 px-6 text-sm backdrop-blur hover:bg-white">
                                <a href="#how-it-works">
                                    See how it works
                                    <Sparkles className="h-4 w-4 text-emerald-600" />
                                </a>
                            </Button>
                        </div>
                        <p className="mt-4 text-xs text-slate-500 home-fade-up" style={{ animationDelay: '0.55s' }}>
                            Single sign-on via Inte.Team · No separate password
                        </p>
                    </div>

                    <Reveal delay={400} className="mx-auto mt-16 max-w-5xl">
                        <HeroMock />
                    </Reveal>
                </div>
            </section>

            <section aria-label="Trust strip" className="relative border-y border-slate-200/70 bg-white/60 py-5 backdrop-blur">
                <div className="overflow-hidden [mask-image:linear-gradient(to_right,transparent,black_10%,black_90%,transparent)]">
                    <div className="flex w-max gap-12 home-marquee">
                        {[...TRUST, ...TRUST].map((label, i) => (
                            <span key={`${label}-${i}`} className="inline-flex shrink-0 items-center gap-2 text-xs font-medium uppercase tracking-wider text-slate-500">
                                <span className="h-1 w-1 rounded-full bg-emerald-500" />
                                {label}
                            </span>
                        ))}
                    </div>
                </div>
            </section>

            <section id="features" className="px-4 py-20 sm:py-28">
                <div className="mx-auto max-w-6xl">
                    <Reveal>
                        <div className="max-w-2xl">
                            <span className="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-emerald-700">
                                <span className="h-px w-6 bg-emerald-500" />
                                What you get
                            </span>
                            <h2 className="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">
                                Built around the actual repair workflow
                            </h2>
                            <p className="mt-3 text-base leading-relaxed text-slate-600">
                                Not a generic CRM. Not a notes app. Every screen exists because mechanics —
                                and their customers — needed it.
                            </p>
                        </div>
                    </Reveal>

                    <div className="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {FEATURES.map(({ icon: Icon, title, body, accent }, i) => (
                            <Reveal key={title} delay={i * 80}>
                                <div className={cn(
                                    'home-card-glow group relative h-full overflow-hidden rounded-2xl border border-slate-200 bg-white p-6',
                                )}>
                                    <div className={cn(
                                        'pointer-events-none absolute -right-12 -top-12 h-40 w-40 rounded-full bg-gradient-to-br opacity-70 blur-2xl',
                                        accent
                                    )} />
                                    <div className="relative">
                                        <div className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-slate-900 text-white shadow-lg shadow-slate-900/10 transition-transform group-hover:scale-110">
                                            <Icon className="h-5 w-5" />
                                        </div>
                                        <h3 className="mt-5 text-base font-semibold text-slate-900">{title}</h3>
                                        <p className="mt-2 text-sm leading-relaxed text-slate-600">{body}</p>
                                    </div>
                                </div>
                            </Reveal>
                        ))}
                    </div>
                </div>
            </section>

            <section id="how-it-works" className="relative overflow-hidden px-4 py-20 sm:py-28">
                <div aria-hidden className="pointer-events-none absolute inset-0 -z-10 home-grid-bg opacity-50" />
                <div className="mx-auto max-w-6xl">
                    <Reveal>
                        <div className="max-w-2xl">
                            <span className="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-emerald-700">
                                <span className="h-px w-6 bg-emerald-500" />
                                Workflow
                            </span>
                            <h2 className="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">
                                Four steps. One job record.
                            </h2>
                            <p className="mt-3 text-base leading-relaxed text-slate-600">
                                From the moment a customer drops the keys, to the moment they drive away.
                            </p>
                        </div>
                    </Reveal>

                    <ol className="relative mt-14 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div aria-hidden className="pointer-events-none absolute left-6 right-6 top-12 hidden h-px bg-gradient-to-r from-transparent via-slate-300 to-transparent lg:block" />
                        {STEPS.map(({ icon: Icon, title, body }, i) => (
                            <Reveal key={title} delay={i * 120}>
                                <li className="relative rounded-2xl border border-slate-200 bg-white p-6 transition-shadow hover:shadow-lg">
                                    <div className="flex items-center justify-between">
                                        <div className="relative inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-slate-900 to-slate-700 text-white shadow-lg">
                                            <Icon className="h-5 w-5" />
                                        </div>
                                        <span className="rounded-full bg-emerald-50 px-2.5 py-0.5 text-[10px] font-semibold tracking-wider text-emerald-700">
                                            STEP {i + 1}
                                        </span>
                                    </div>
                                    <h3 className="mt-5 text-base font-semibold text-slate-900">{title}</h3>
                                    <p className="mt-2 text-sm leading-relaxed text-slate-600">{body}</p>
                                </li>
                            </Reveal>
                        ))}
                    </ol>
                </div>
            </section>

            <section id="portal" className="relative overflow-hidden bg-slate-950 px-4 py-20 text-white sm:py-28">
                <div aria-hidden className="pointer-events-none absolute inset-0">
                    <div className="absolute inset-0 home-dot-bg opacity-60" />
                    <div className="absolute -top-32 left-1/4 h-96 w-96 rounded-full bg-emerald-500/20 blur-3xl home-blob" />
                    <div className="absolute -bottom-32 right-1/4 h-96 w-96 rounded-full bg-indigo-500/20 blur-3xl home-blob" style={{ animationDelay: '-9s' }} />
                </div>
                <div className="relative mx-auto grid max-w-6xl gap-14 lg:grid-cols-2 lg:items-center">
                    <Reveal>
                        <span className="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3 py-1 text-xs font-medium text-emerald-300 backdrop-blur">
                            <Lock className="h-3 w-3" />
                            For your customers
                        </span>
                        <h2 className="mt-5 text-3xl font-semibold tracking-tight sm:text-4xl">
                            A portal they actually use — because there's nothing to learn.
                        </h2>
                        <p className="mt-4 max-w-xl text-base leading-relaxed text-slate-300">
                            One signed link, sent via SMS or email through your existing CRM. No login, no app
                            to install. They see the photos you took, the estimate you wrote, and a button to approve.
                        </p>
                        <ul className="mt-7 grid gap-3 sm:grid-cols-2">
                            {[
                                'Approve / decline each line item',
                                'Pin a question to a specific photo',
                                'Handover inspection + payment',
                                'Reads in their own language',
                            ].map((item) => (
                                <li key={item} className="flex items-start gap-2.5 text-sm text-slate-200">
                                    <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-emerald-400" />
                                    <span>{item}</span>
                                </li>
                            ))}
                        </ul>
                        <div className="mt-8 flex flex-wrap gap-4 text-xs text-slate-400">
                            <span className="inline-flex items-center gap-1.5"><Cloud className="h-3.5 w-3.5" /> Google Cloud media</span>
                            <span className="inline-flex items-center gap-1.5"><Clock className="h-3.5 w-3.5" /> 24h timeout chasers</span>
                            <span className="inline-flex items-center gap-1.5"><ShieldCheck className="h-3.5 w-3.5" /> Append-only audit</span>
                        </div>
                    </Reveal>

                    <Reveal delay={150}>
                        <div className="relative mx-auto w-full max-w-sm">
                            <div className="absolute -inset-6 rounded-[36px] bg-gradient-to-br from-emerald-500/30 via-sky-500/20 to-indigo-500/30 blur-2xl" />
                            <div className="relative rounded-[28px] border border-white/10 bg-white/[0.04] p-3 backdrop-blur-xl home-float">
                                <div className="rounded-[22px] bg-white p-5 text-slate-900 shadow-2xl">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <span className="inline-flex h-6 w-6 items-center justify-center rounded-lg bg-slate-900 text-white">
                                                <Wrench className="h-3.5 w-3.5" />
                                            </span>
                                            <span className="text-xs font-semibold">AutoFix Garage</span>
                                        </div>
                                        <span className="rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700">
                                            Awaiting approval
                                        </span>
                                    </div>
                                    <p className="mt-4 text-[10px] font-medium uppercase tracking-wider text-slate-500">BD23 KLM · Ford Focus</p>
                                    <h3 className="mt-1 text-sm font-semibold">Estimate — 3 items</h3>
                                    <ul className="mt-3 space-y-2 text-xs">
                                        <PortalLine label="Front brake pads" price="£128.00" status="ok" />
                                        <PortalLine label="Brake fluid change" price="£45.00" status="ok" />
                                        <PortalLine label="Rear discs (scope change)" price="£210.00" status="new" />
                                    </ul>
                                    <button type="button" className="mt-4 w-full rounded-lg bg-gradient-to-r from-emerald-600 to-emerald-500 px-3 py-2 text-xs font-semibold text-white shadow-lg shadow-emerald-600/20">
                                        Approve all
                                    </button>
                                    <p className="mt-3 text-center text-[10px] text-slate-400">
                                        Secure signed link · expires in 24h
                                    </p>
                                </div>
                            </div>
                        </div>
                    </Reveal>
                </div>
            </section>

            <section className="px-4 py-20 sm:py-28">
                <Reveal>
                    <div className="relative mx-auto max-w-4xl overflow-hidden rounded-3xl border border-slate-200 bg-gradient-to-br from-white via-slate-50 to-emerald-50/40 p-10 text-center shadow-xl shadow-slate-900/[0.04] sm:p-14">
                        <div aria-hidden className="pointer-events-none absolute -right-20 -top-20 h-64 w-64 rounded-full bg-emerald-300/30 blur-3xl home-blob" />
                        <div aria-hidden className="pointer-events-none absolute -bottom-20 -left-20 h-64 w-64 rounded-full bg-sky-300/30 blur-3xl home-blob" style={{ animationDelay: '-7s' }} />
                        <div className="relative">
                            <h2 className="text-3xl font-semibold tracking-tight sm:text-4xl">
                                Ready to put the paperwork behind you?
                            </h2>
                            <p className="mx-auto mt-3 max-w-xl text-base leading-relaxed text-slate-600">
                                Sign in with your Inte.Team account. Your garage, your mechanics, your jobs — already linked.
                            </p>
                            <div className="mt-7 flex justify-center">
                                <Button asChild size="lg" className="group h-12 rounded-full bg-slate-900 px-7 text-sm shadow-lg shadow-slate-900/10 hover:bg-slate-800">
                                    <a href="/sign-in">
                                        Sign in
                                        <ArrowRight className="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                                    </a>
                                </Button>
                            </div>
                        </div>
                    </div>
                </Reveal>
            </section>

            <footer className="border-t border-slate-200 bg-white px-4 py-8">
                <div className="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 text-xs text-slate-500 sm:flex-row">
                    <div className="flex items-center gap-2">
                        <Wrench className="h-3.5 w-3.5" />
                        <span>InteTeam Garage · {new Date().getFullYear()}</span>
                    </div>
                    <div className="flex items-center gap-4">
                        <a href="https://inte.team" className="transition-colors hover:text-slate-900" target="_blank" rel="noreferrer">
                            Inte.Team
                        </a>
                        <a href="/sign-in" className="transition-colors hover:text-slate-900">
                            Sign in
                        </a>
                    </div>
                </div>
            </footer>
        </div>
    );
}

function PortalLine({ label, price, status }: { label: string; price: string; status: 'ok' | 'new' }) {
    return (
        <li className={cn(
            'flex items-center justify-between rounded-lg border px-3 py-2',
            status === 'new' ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200'
        )}>
            <span className={cn('truncate', status === 'new' ? 'text-emerald-900' : 'text-slate-700')}>{label}</span>
            <span className={cn('font-semibold', status === 'new' ? 'text-emerald-900' : 'text-slate-900')}>{price}</span>
        </li>
    );
}

function HeroMock() {
    return (
        <div className="relative">
            <div aria-hidden className="absolute -inset-6 rounded-[36px] bg-gradient-to-br from-emerald-400/20 via-sky-400/20 to-indigo-400/20 blur-2xl" />
            <div className="relative grid grid-cols-1 gap-3 rounded-2xl border border-slate-200/80 bg-white/80 p-3 shadow-2xl shadow-slate-900/[0.08] backdrop-blur-xl md:grid-cols-3">
                <div className="rounded-xl border border-slate-200 bg-white p-4">
                    <div className="flex items-center justify-between">
                        <span className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Active jobs</span>
                        <span className="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">+3 today</span>
                    </div>
                    <p className="mt-3 text-3xl font-semibold tracking-tight">12</p>
                    <div className="mt-3 flex items-end gap-1">
                        {[40, 55, 35, 70, 50, 80, 60].map((h, i) => (
                            <span key={i} className="w-2 rounded-sm bg-gradient-to-t from-emerald-500/30 to-emerald-500" style={{ height: `${h * 0.4}px` }} />
                        ))}
                    </div>
                </div>
                <div className="rounded-xl border border-slate-200 bg-white p-4 md:col-span-2">
                    <div className="flex items-center justify-between">
                        <span className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Latest job</span>
                        <span className="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700">
                            <span className="h-1.5 w-1.5 rounded-full bg-amber-500" />
                            Awaiting approval
                        </span>
                    </div>
                    <p className="mt-3 text-base font-semibold">BD23 KLM · Ford Focus</p>
                    <p className="text-xs text-slate-500">3 stages documented · 9 photos · estimate sent 12 min ago</p>
                    <div className="mt-3 grid grid-cols-3 gap-2">
                        {['Pre-inspection', 'Disassembly', 'Fault found'].map((stage, i) => (
                            <div key={stage} className="rounded-lg border border-slate-200 bg-slate-50 p-2">
                                <div className="flex items-center gap-1 text-[10px] font-medium text-slate-600">
                                    <CheckCircle2 className="h-3 w-3 text-emerald-500" />
                                    {stage}
                                </div>
                                <div className="mt-1.5 flex gap-1">
                                    {Array.from({ length: 3 }).map((_, j) => (
                                        <span key={j} className={cn(
                                            'h-6 flex-1 rounded',
                                            i === 0 && 'bg-gradient-to-br from-emerald-200 to-emerald-300',
                                            i === 1 && 'bg-gradient-to-br from-sky-200 to-sky-300',
                                            i === 2 && 'bg-gradient-to-br from-amber-200 to-amber-300',
                                        )} />
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}
