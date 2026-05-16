<?php
add_action('graphql_register_types', function() {
    register_graphql_input_type('QuoteQuantityInput', [
        'description' => 'A quantity line item for a quote request',
        'fields' => [
            'item' => ['type' => 'String'],
            'amount' => ['type' => 'String'],
            'unit' => ['type' => 'String'],
        ],
    ]);

    register_graphql_input_type('CreateMilestoneInput', [
        'description' => 'Input for creating a project milestone',
        'fields' => [
            'projectId' => ['type' => 'Int', 'description' => 'Project ID'],
            'title' => ['type' => 'String', 'description' => 'Milestone title'],
            'description' => ['type' => 'String'],
            'phase' => ['type' => 'String', 'description' => 'Project phase (Design, Build, QA, etc)'],
            'dueDate' => ['type' => 'String', 'description' => 'ISO date string YYYY-MM-DD'],
            'deliverables' => ['type' => 'String'],
        ],
    ]);

    register_graphql_input_type('AssignTeamMemberInput', [
        'description' => 'Input for assigning a team member to a project',
        'fields' => [
            'projectId' => ['type' => 'Int'],
            'userId' => ['type' => 'Int'],
            'role' => ['type' => 'String', 'description' => 'Project Manager, Site Supervisor, Foreman, etc'],
            'responsibility' => ['type' => 'String'],
        ],
    ]);

    // Object Types
    register_graphql_object_type('ProjectMetadata', [
        'description' => 'Extended project metadata and governance',
        'fields' => [
            'id' => ['type' => 'Int'],
            'projectId' => ['type' => 'Int'],
            'projectOwnerId' => ['type' => 'Int', 'description' => 'Executive sponsor'],
            'projectManagerId' => ['type' => 'Int', 'description' => 'Day-to-day project lead'],
            'clientName' => ['type' => 'String'],
            'location' => ['type' => 'String'],
            'budgetContingencyPct' => ['type' => 'Float', 'description' => 'Reserve percentage (10-20%)'],
            'qualityStandard' => ['type' => 'String', 'description' => 'ISO, ADA, LEED, Fire Code, etc'],
            'contractType' => ['type' => 'String', 'description' => 'fixed_price, time_materials, design_build, other'],
            'currency' => ['type' => 'String', 'description' => 'ISO 3-letter code'],
        ],
    ]);

    register_graphql_object_type('ProjectMilestone', [
        'description' => 'A project milestone with status tracking',
        'fields' => [
            'id' => ['type' => 'Int'],
            'projectId' => ['type' => 'Int'],
            'title' => ['type' => 'String'],
            'description' => ['type' => 'String'],
            'phase' => ['type' => 'String'],
            'dueDate' => ['type' => 'String', 'description' => 'ISO date string'],
            'completionDate' => ['type' => 'String'],
            'status' => ['type' => 'String', 'description' => 'not_started, in_progress, on_hold, completed, at_risk'],
            'deliverables' => ['type' => 'String'],
        ],
    ]);

    register_graphql_object_type('ProjectTeamMember', [
        'description' => 'A team member assigned to a project',
        'fields' => [
            'id' => ['type' => 'Int'],
            'projectId' => ['type' => 'Int'],
            'userId' => ['type' => 'Int'],
            'userLogin' => ['type' => 'String', 'description' => 'WordPress username'],
            'userEmail' => ['type' => 'String'],
            'displayName' => ['type' => 'String'],
            'role' => ['type' => 'String', 'description' => 'Project Manager, Site Supervisor, etc'],
            'responsibility' => ['type' => 'String'],
        ],
    ]);

    register_graphql_object_type('ProjectDocument', [
        'description' => 'A project document with version control',
        'fields' => [
            'id' => ['type' => 'Int'],
            'projectId' => ['type' => 'Int'],
            'documentType' => ['type' => 'String', 'description' => 'charter, schedule, budget, etc'],
            'title' => ['type' => 'String'],
            'fileUrl' => ['type' => 'String'],
            'version' => ['type' => 'String'],
            'status' => ['type' => 'String', 'description' => 'draft, approved, archived'],
            'createdBy' => ['type' => 'Int'],
            'createdAt' => ['type' => 'String'],
        ],
    ]);

    register_graphql_object_type('Supplier', [
        'description' => 'A supplier profile used by procurement workflows',
        'fields' => [
            'id' => ['type' => 'Int'],
            'name' => ['type' => 'String'],
            'kraPin' => [
                'type' => 'String',
                'resolve' => function($supplier) {
                    return $supplier['kra_pin'] ?? null;
                },
            ],
            'contactName' => [
                'type' => 'String',
                'resolve' => function($supplier) {
                    return $supplier['contact_name'] ?? null;
                },
            ],
            'contactEmail' => [
                'type' => 'String',
                'resolve' => function($supplier) {
                    return $supplier['contact_email'] ?? null;
                },
            ],
            'contactPhone' => [
                'type' => 'String',
                'resolve' => function($supplier) {
                    return $supplier['contact_phone'] ?? null;
                },
            ],
            'paymentTerms' => [
                'type' => 'String',
                'resolve' => function($supplier) {
                    return $supplier['payment_terms'] ?? null;
                },
            ],
            'notes' => ['type' => 'String'],
            'createdAt' => [
                'type' => 'String',
                'resolve' => function($supplier) {
                    return $supplier['created_at'] ?? null;
                },
            ],
            'updatedAt' => [
                'type' => 'String',
                'resolve' => function($supplier) {
                    return $supplier['updated_at'] ?? null;
                },
            ],
        ],
    ]);

    register_graphql_object_type('Worker', [
        'description' => 'A casual worker profile used by field operations',
        'fields' => [
            'id' => ['type' => 'Int'],
            'fullName' => [
                'type' => 'String',
                'resolve' => function($worker) {
                    return $worker['full_name'] ?? null;
                },
            ],
            'nationalId' => [
                'type' => 'String',
                'resolve' => function($worker) {
                    return $worker['national_id'] ?? null;
                },
            ],
            'nssfNumber' => [
                'type' => 'String',
                'resolve' => function($worker) {
                    return $worker['nssf_number'] ?? null;
                },
            ],
            'nhifNumber' => [
                'type' => 'String',
                'resolve' => function($worker) {
                    return $worker['nhif_number'] ?? null;
                },
            ],
            'skillType' => [
                'type' => 'String',
                'resolve' => function($worker) {
                    return $worker['skill_type'] ?? null;
                },
            ],
            'dailyRate' => [
                'type' => 'Float',
                'resolve' => function($worker) {
                    return isset($worker['daily_rate']) ? (float) $worker['daily_rate'] : 0.0;
                },
            ],
            'phone' => [
                'type' => 'String',
                'resolve' => function($worker) {
                    return $worker['phone'] ?? null;
                },
            ],
            'isActive' => [
                'type' => 'Boolean',
                'resolve' => function($worker) {
                    return !empty($worker['is_active']);
                },
            ],
            'createdAt' => [
                'type' => 'String',
                'resolve' => function($worker) {
                    return $worker['created_at'] ?? null;
                },
            ],
            'updatedAt' => [
                'type' => 'String',
                'resolve' => function($worker) {
                    return $worker['updated_at'] ?? null;
                },
            ],
        ],
    ]);

    // Register Project type with expanded fields
    register_graphql_object_type('ConstructionProject', [
        'description' => 'A construction project with full PMBOK governance',
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
            ],
            'startDate' => ['type' => 'String', 'description' => 'ISO date string'],
            'endDate' => ['type' => 'String', 'description' => 'ISO date string'],
            'metadata' => [
                'type' => 'ProjectMetadata',
                'resolve' => function($project) {
                    return construction_mgmt_get_project_metadata($project['id']) ?: [];
                }
            ],
            'milestones' => [
                'type' => ['list_of' => 'ProjectMilestone'],
                'resolve' => function($project) {
                    return construction_mgmt_get_project_milestones($project['id']) ?: [];
                }
            ],
            'teamMembers' => [
                'type' => ['list_of' => 'ProjectTeamMember'],
                'resolve' => function($project) {
                    return construction_mgmt_get_project_team($project['id']) ?: [];
                }
            ],
            'documents' => [
                'type' => ['list_of' => 'ProjectDocument'],
                'resolve' => function($project) {
                    return construction_mgmt_get_project_documents($project['id']) ?: [];
                }
            ],
        ]
    ]);

    register_graphql_object_type('QuoteRequestSubmissionResult', [
        'description' => 'Result of a quote request submission',
        'fields' => [
            'success' => ['type' => 'Boolean'],
            'requestId' => ['type' => 'Int'],
            'message' => ['type' => 'String'],
        ],
    ]);

    register_graphql_object_type('GraphQLAuthDebug', [
        'description' => 'Debug details for current GraphQL authentication context',
        'fields' => [
            'isAuthenticated' => ['type' => 'Boolean'],
            'userId' => ['type' => 'Int'],
            'userLogin' => ['type' => 'String'],
            'roles' => ['type' => ['list_of' => 'String']],
            'canReadProjects' => ['type' => 'Boolean'],
            'canCreateProjects' => ['type' => 'Boolean'],
            'canManageConstructionProjects' => ['type' => 'Boolean'],
            'canManageOptions' => ['type' => 'Boolean'],
            'authHeaderPresent' => ['type' => 'Boolean'],
            'tokenPresent' => ['type' => 'Boolean'],
            'tokenAlg' => ['type' => 'String'],
            'secretSource' => ['type' => 'String'],
            'authError' => ['type' => 'String'],
        ],
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
            $projects_table = construction_mgmt_get_table_name('projects');
            $sql = "SELECT * FROM {$projects_table}";
            if (!empty($args['status'])) {
                $sql .= $wpdb->prepare(" WHERE status = %s", $args['status']);
            }
            return $wpdb->get_results($sql, ARRAY_A);
        }
    ]);

    // Query: authDebug
    register_graphql_field('RootQuery', 'authDebug', [
        'type' => 'GraphQLAuthDebug',
        'resolve' => function() {
            $user = wp_get_current_user();
            $user_id = get_current_user_id();
            $is_authenticated = $user_id > 0 && $user && $user->exists();
            $jwt_debug = function_exists('construction_mgmt_get_graphql_auth_debug')
                ? construction_mgmt_get_graphql_auth_debug()
                : [];

            return [
                'isAuthenticated' => $is_authenticated,
                'userId' => $is_authenticated ? (int) $user_id : 0,
                'userLogin' => $is_authenticated ? (string) $user->user_login : '',
                'roles' => $is_authenticated ? array_values((array) $user->roles) : [],
                'canReadProjects' => current_user_can('read_projects'),
                'canCreateProjects' => current_user_can('create_projects'),
                'canManageConstructionProjects' => current_user_can('manage_construction_projects'),
                'canManageOptions' => current_user_can('manage_options'),
                'authHeaderPresent' => !empty($jwt_debug['header_present']),
                'tokenPresent' => !empty($jwt_debug['token_present']),
                'tokenAlg' => isset($jwt_debug['token_alg']) ? (string) $jwt_debug['token_alg'] : '',
                'secretSource' => isset($jwt_debug['secret_source']) ? (string) $jwt_debug['secret_source'] : '',
                'authError' => isset($jwt_debug['error']) ? (string) $jwt_debug['error'] : '',
            ];
        },
    ]);

    // Query: project (single)
    register_graphql_field('RootQuery', 'project', [
        'type' => 'ConstructionProject',
        'args' => [
            'id' => ['type' => 'Int', 'description' => 'Project ID']
        ],
        'resolve' => function($source, $args, $context, $info) {
            if (!current_user_can('read_projects')) return null;
            return construction_mgmt_get_project($args['id']);
        }
    ]);

    register_graphql_field('RootQuery', 'suppliers', [
        'type' => ['list_of' => 'Supplier'],
        'resolve' => function() {
            if (!current_user_can('read_projects') && !current_user_can('manage_construction_projects')) {
                return [];
            }

            return construction_mgmt_get_suppliers();
        }
    ]);

    register_graphql_field('RootQuery', 'workers', [
        'type' => ['list_of' => 'Worker'],
        'resolve' => function() {
            if (!current_user_can('read_projects') && !current_user_can('manage_construction_projects')) {
                return [];
            }

            return construction_mgmt_get_workers();
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

            $project_id = construction_mgmt_create_project(
                isset($input['name']) ? $input['name'] : '',
                isset($input['description']) ? $input['description'] : '',
                isset($input['budgetTotal']) ? (float) $input['budgetTotal'] : 0,
                null,
                null,
                get_current_user_id()
            );

            return ['project' => construction_mgmt_get_project($project_id)];
        }
    ]);

    register_graphql_mutation('submitQuoteRequest', [
        'inputFields' => [
            'projectType' => ['type' => 'String'],
            'projectScope' => ['type' => 'String'],
            'quantities' => ['type' => ['list_of' => 'QuoteQuantityInput']],
            'qualitativeSpecs' => ['type' => 'String'],
            'contactName' => ['type' => 'String'],
            'contactEmail' => ['type' => 'String'],
            'contactPhone' => ['type' => 'String'],
            'contactCompany' => ['type' => 'String'],
        ],
        'outputFields' => [
            'success' => ['type' => 'Boolean'],
            'requestId' => ['type' => 'Int'],
            'message' => ['type' => 'String'],
        ],
        'mutateAndGetPayload' => function($input, $context, $info) {
            $request_id = construction_mgmt_create_quote_request($input);

            if (is_wp_error($request_id)) {
                throw new \GraphQL\Error\UserError($request_id->get_error_message());
            }

            return [
                'success' => true,
                'requestId' => (int) $request_id,
                'message' => 'Quote request received. Our team will review it shortly.',
            ];
        }
    ]);

    // Mutation: createMilestone
    register_graphql_mutation('createMilestone', [
        'inputFields' => [
            'projectId' => ['type' => 'Int'],
            'title' => ['type' => 'String'],
            'description' => ['type' => 'String'],
            'phase' => ['type' => 'String'],
            'dueDate' => ['type' => 'String'],
            'deliverables' => ['type' => 'String'],
        ],
        'outputFields' => [
            'milestone' => ['type' => 'ProjectMilestone'],
            'success' => ['type' => 'Boolean'],
            'message' => ['type' => 'String'],
        ],
        'mutateAndGetPayload' => function($input, $context, $info) {
            if (!current_user_can('manage_construction_projects')) {
                throw new \GraphQL\Error\UserError('Permission denied.');
            }

            $milestone_data = [
                'title' => $input['title'] ?? '',
                'description' => $input['description'] ?? '',
                'phase' => $input['phase'] ?? '',
                'due_date' => $input['dueDate'] ?? '',
                'deliverables' => $input['deliverables'] ?? '',
                'status' => 'not_started',
            ];

            $milestone_id = construction_mgmt_create_milestone($input['projectId'], $milestone_data);

            if (is_wp_error($milestone_id)) {
                throw new \GraphQL\Error\UserError($milestone_id->get_error_message());
            }

            $milestone = $GLOBALS['wpdb']->get_row(
                $GLOBALS['wpdb']->prepare(
                    "SELECT * FROM " . construction_mgmt_get_table_name('project_milestones') . " WHERE id = %d",
                    $milestone_id
                ),
                ARRAY_A
            );

            return [
                'milestone' => $milestone,
                'success' => true,
                'message' => 'Milestone created successfully.',
            ];
        }
    ]);

    // Mutation: assignTeamMember
    register_graphql_mutation('assignTeamMember', [
        'inputFields' => [
            'projectId' => ['type' => 'Int'],
            'userId' => ['type' => 'Int'],
            'role' => ['type' => 'String'],
            'responsibility' => ['type' => 'String'],
        ],
        'outputFields' => [
            'teamMember' => ['type' => 'ProjectTeamMember'],
            'success' => ['type' => 'Boolean'],
            'message' => ['type' => 'String'],
        ],
        'mutateAndGetPayload' => function($input, $context, $info) {
            if (!current_user_can('manage_construction_projects')) {
                throw new \GraphQL\Error\UserError('Permission denied.');
            }

            construction_mgmt_assign_team_member(
                $input['projectId'],
                $input['userId'],
                $input['role'] ?? '',
                $input['responsibility'] ?? ''
            );

            $team_member = $GLOBALS['wpdb']->get_row(
                $GLOBALS['wpdb']->prepare(
                    "SELECT t.*, u.user_login, u.user_email, u.display_name 
                     FROM " . construction_mgmt_get_table_name('project_team') . " t
                     LEFT JOIN {$GLOBALS['wpdb']->users} u ON t.user_id = u.ID
                     WHERE t.project_id = %d AND t.user_id = %d",
                    $input['projectId'],
                    $input['userId']
                ),
                ARRAY_A
            );

            return [
                'teamMember' => $team_member,
                'success' => true,
                'message' => 'Team member assigned successfully.',
            ];
        }
    ]);

    register_graphql_field('RootMutation', 'createSupplier', [
        'type' => 'Supplier',
        'args' => [
            'name' => ['type' => 'String'],
            'kraPin' => ['type' => 'String'],
            'contactName' => ['type' => 'String'],
            'contactEmail' => ['type' => 'String'],
            'contactPhone' => ['type' => 'String'],
            'paymentTerms' => ['type' => 'String'],
            'notes' => ['type' => 'String'],
        ],
        'resolve' => function($source, $args) {
            if (!current_user_can('manage_construction_projects')) {
                throw new \GraphQL\Error\UserError('Permission denied.');
            }

            $supplier = construction_mgmt_create_supplier($args);
            if (is_wp_error($supplier)) {
                throw new \GraphQL\Error\UserError($supplier->get_error_message());
            }

            return $supplier;
        }
    ]);

    register_graphql_field('RootMutation', 'updateSupplier', [
        'type' => 'Supplier',
        'args' => [
            'id' => ['type' => 'ID'],
            'name' => ['type' => 'String'],
            'kraPin' => ['type' => 'String'],
            'contactName' => ['type' => 'String'],
            'contactEmail' => ['type' => 'String'],
            'contactPhone' => ['type' => 'String'],
            'paymentTerms' => ['type' => 'String'],
            'notes' => ['type' => 'String'],
        ],
        'resolve' => function($source, $args) {
            if (!current_user_can('manage_construction_projects')) {
                throw new \GraphQL\Error\UserError('Permission denied.');
            }

            $supplier = construction_mgmt_update_supplier($args['id'] ?? 0, $args);
            if (is_wp_error($supplier)) {
                throw new \GraphQL\Error\UserError($supplier->get_error_message());
            }

            return $supplier;
        }
    ]);

    register_graphql_field('RootMutation', 'deleteSupplier', [
        'type' => 'Boolean',
        'args' => [
            'id' => ['type' => 'ID'],
        ],
        'resolve' => function($source, $args) {
            if (!current_user_can('manage_construction_projects')) {
                throw new \GraphQL\Error\UserError('Permission denied.');
            }

            $deleted = construction_mgmt_delete_supplier($args['id'] ?? 0);
            if (is_wp_error($deleted)) {
                throw new \GraphQL\Error\UserError($deleted->get_error_message());
            }

            return true;
        }
    ]);

    register_graphql_field('RootMutation', 'createWorker', [
        'type' => 'Worker',
        'args' => [
            'fullName' => ['type' => 'String'],
            'nationalId' => ['type' => 'String'],
            'nssfNumber' => ['type' => 'String'],
            'nhifNumber' => ['type' => 'String'],
            'skillType' => ['type' => 'String'],
            'dailyRate' => ['type' => 'Float'],
            'phone' => ['type' => 'String'],
            'isActive' => ['type' => 'Boolean'],
        ],
        'resolve' => function($source, $args) {
            if (!current_user_can('manage_construction_projects')) {
                throw new \GraphQL\Error\UserError('Permission denied.');
            }

            $worker = construction_mgmt_create_worker($args);
            if (is_wp_error($worker)) {
                throw new \GraphQL\Error\UserError($worker->get_error_message());
            }

            return $worker;
        }
    ]);

    register_graphql_field('RootMutation', 'updateWorker', [
        'type' => 'Worker',
        'args' => [
            'id' => ['type' => 'ID'],
            'fullName' => ['type' => 'String'],
            'nationalId' => ['type' => 'String'],
            'nssfNumber' => ['type' => 'String'],
            'nhifNumber' => ['type' => 'String'],
            'skillType' => ['type' => 'String'],
            'dailyRate' => ['type' => 'Float'],
            'phone' => ['type' => 'String'],
            'isActive' => ['type' => 'Boolean'],
        ],
        'resolve' => function($source, $args) {
            if (!current_user_can('manage_construction_projects')) {
                throw new \GraphQL\Error\UserError('Permission denied.');
            }

            $worker = construction_mgmt_update_worker($args['id'] ?? 0, $args);
            if (is_wp_error($worker)) {
                throw new \GraphQL\Error\UserError($worker->get_error_message());
            }

            return $worker;
        }
    ]);

    register_graphql_field('RootMutation', 'deleteWorker', [
        'type' => 'Boolean',
        'args' => [
            'id' => ['type' => 'ID'],
        ],
        'resolve' => function($source, $args) {
            if (!current_user_can('manage_construction_projects')) {
                throw new \GraphQL\Error\UserError('Permission denied.');
            }

            $deleted = construction_mgmt_delete_worker($args['id'] ?? 0);
            if (is_wp_error($deleted)) {
                throw new \GraphQL\Error\UserError($deleted->get_error_message());
            }

            return true;
        }
    ]);
});