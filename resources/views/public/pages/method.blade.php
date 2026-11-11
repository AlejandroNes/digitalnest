@extends('public.pages.page', ['title' => 'Cómo probamos las herramientas', 'lede' => 'Lo que hacemos antes de publicar una ficha, un flujo o una plantilla, y cómo ponemos la nota.'])

@section('seo')
    <x-seo title="Cómo probamos las herramientas" description="Nuestro método: usamos cada herramienta en tareas reales, medimos precio, plan gratis y español, y ponemos una nota del 1 al 10 con fecha." />
@endsection

@section('body')
    <h2>La usamos en una tarea real</h2>
    <p>No resumimos la web del fabricante. Cada herramienta la usamos para un trabajo concreto de freelance o pequeño negocio: escribir un texto, editar un video, automatizar un proceso. La ficha cuenta qué hicimos y qué salió.</p>

    <h2>Medimos lo que te importa</h2>
    <ul>
        <li><strong>Precio real en USD</strong> del plan de pago más bajo, y si el plan gratis sirve de verdad o solo para mirar.</li>
        <li><strong>Si pide tarjeta</strong> para empezar la prueba.</li>
        <li><strong>Cómo funciona en español</strong>: interfaz, comprensión y calidad de lo que genera.</li>
        <li><strong>Pagos desde Latinoamérica</strong>, cuando lo hemos podido comprobar. Si no, lo marcamos como «sin verificar».</li>
        <li><strong>Privacidad</strong>: si usa tus datos para entrenar y dónde los guarda, según su política.</li>
    </ul>

    <h2>Ponemos una nota del 1 al 10</h2>
    <ul>
        <li><strong>9–10, Excelente:</strong> la recomendamos sin reservas para su uso principal.</li>
        <li><strong>7–8, Bueno:</strong> cumple bien, con limitaciones que conviene conocer.</li>
        <li><strong>5–6, Aceptable:</strong> sirve en casos concretos; probablemente haya mejores opciones.</li>
        <li><strong>1–4, Flojo:</strong> no la recomendamos para el uso que probamos.</li>
    </ul>

    <h2>Fecha y revisión</h2>
    <p>Cada ficha muestra cuándo la probamos y cuándo la revisamos por última vez. Las herramientas cambian rápido: si cambian precios o funciones importantes, actualizamos la ficha y su fecha.</p>

    <h2>Independencia</h2>
    <p>Algunos enlaces son de afiliado. Eso no cambia la nota ni el orden de las recomendaciones. <a href="{{ route('pages.affiliates') }}">Cómo funcionan los enlaces de afiliado</a>.</p>

    <p>¿Ves algo desactualizado o quieres que probemos una herramienta? <a href="{{ route('contact.create') }}">Escríbenos</a>.</p>
@endsection
