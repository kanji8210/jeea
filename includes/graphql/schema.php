<?php
add_action('graphql_register_types', function() {
    // Register Project type
    register_graphql_object_type('ConstructionProject', [
        'description' => 'A construction project',
        'fields' => [
            'id' => ['type' => 'Int'],
            'name' => ['type' => 'String'],
            'description' => ['type' => 'String'],
            'status' => ['type' => 'String'],
            'budgetTotal' => ['type' => 'Float'],
            'budgetSpent' => ['type' => 'Float'],
            'progressPercent' => [
                'type' => 'Float',
                'resolve' => function($project) {
                    if ($project['budget_total'] == 0) return 0;
                    return round(($project['budget_spent'] / $project['budget_total']) * 100, 2);
                }
            ]
        ]
    ]);

    // Query: projects
    register_graphql_field('RootQuery', 'projects', [
        'type' => ['list_of' => 'ConstructionProject'],
        'args' => [
            'status' => ['type' => 'String']
        ],
        'resolve' => function($source, $args, $context, $info) {
            if (!current_user_can('read_projects')) return [];
            global $wpdb;
            $sql = "SELECT * FROM {$wpdb->prefix}const_projects";
            if (!empty($args['status'])) {
                $sql .= $wpdb->prepare(" WHERE status = %s", $args['status']);
            }
            return $wpdb->get_results($sql, ARRAY_A);
        }
    ]);

    // Mutation: createProject
    register_graphql_mutation('createProject', [
        'inputFields' => [
            'name' => ['type' => 'String', 'description' => 'Project name'],
            'description' => ['type' => 'String'],
            'budgetTotal' => ['type' => 'Float']
        ],
        'outputFields' => [
            'project' => ['type' => 'ConstructionProject']
        ],
        'mutateAndGetPayload' => function($input, $context, $info) {
            if (!current_user_can('create_projects')) {
                throw new \GraphQL\Error\UserError('Permission denied');
            }
            $project_id = construction_mgmt_create_project([
                'name' => $input['name'],
                'description' => $input['description'] ?? '',
                'budget_total' => $input['budgetTotal'] ?? 0
            ]);
            return ['project' => construction_mgmt_get_project($project_id)];
        }
    ]);
});