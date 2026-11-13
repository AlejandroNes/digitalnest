@extends('public.pages.page', ['title' => 'Afiliados y transparencia', 'lede' => 'Cómo se financia DigitalNest y qué significa que un enlace sea de afiliado.'])

@section('seo')
    <x-seo title="Afiliados y transparencia" description="Cómo funcionan los enlaces de afiliado en DigitalNest y por qué no cambian nuestras notas ni recomendaciones." />
@endsection

@section('body')
    <h2>Qué es un enlace de afiliado</h2>
    <p>Algunas herramientas tienen programas de afiliados: si llegas a su web desde nuestro enlace y contratas un plan, nos pagan una comisión. <strong>A ti no te cuesta nada extra</strong>; pagas el mismo precio que yendo directamente.</p>

    <h2>Cómo los reconoces</h2>
    <p>Cuando el botón de una ficha lleva a un enlace de afiliado, lo indicamos justo al lado con el texto «Enlace de afiliado». Esos enlaces llevan además la marca <code>rel="sponsored"</code>, como piden los buscadores.</p>

    <h2>Lo que no cambia</h2>
    <ul>
        <li><strong>La nota</strong> se decide con la prueba, tenga o no programa de afiliados la herramienta.</li>
        <li><strong>Las recomendaciones y alternativas</strong> incluyen herramientas sin comisión cuando son mejores para tu caso.</li>
        <li><strong>No aceptamos pagos</strong> por subir la nota ni por aparecer como recomendada.</li>
    </ul>

    <h2>Por qué lo hacemos</h2>
    <p>Probar herramientas lleva tiempo y algunas suscripciones cuestan dinero. Las comisiones nos permiten seguir publicando contenido gratis y sin llenar el sitio de publicidad.</p>

    <p>Más detalles sobre nuestro proceso en <a href="{{ route('pages.method') }}">Cómo probamos las herramientas</a>.</p>
@endsection
