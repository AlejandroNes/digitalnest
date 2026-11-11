@foreach($configuration['field_schema'] as $field)
    @include('components.contact-assistant-field', ['field' => $field, 'configuration' => $configuration, 'first' => $loop->first])
@endforeach
