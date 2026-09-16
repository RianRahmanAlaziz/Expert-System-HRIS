<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            // User
            'user.view',
            'user.create',
            'user.update',
            'user.delete',

            // Role
            'role.view',
            'role.create',
            'role.update',
            'role.delete',

            // Permission
            'permission.view',

            // Department
            'department.view',
            'department.create',
            'department.update',
            'department.delete',

            // Position
            'position.view',
            'position.create',
            'position.update',
            'position.delete',

            // Position Requirement
            'position_requirement.view',
            'position_requirement.create',
            'position_requirement.update',
            'position_requirement.delete',

            // Position Requirement Competency
            'position_requirement_competency.view',
            'position_requirement_competency.create',
            'position_requirement_competency.update',
            'position_requirement_competency.delete',

            // Employee
            'employee.view',
            'employee.create',
            'employee.update',
            'employee.delete',

            // Attendance
            'attendance.view',
            'attendance.clock_in',
            'attendance.clock_out',
            'attendance.view_all',
            'attendance.report',

            // Leave
            'leave_type.view',
            'leave_type.create',
            'leave_type.update',
            'leave_type.delete',

            'leave_balance.view',
            'leave_balance.view_all',

            'leave_request.view',
            'leave_request.create',
            'leave_request.approve',
            'leave_request.reject',
            'leave_request.cancel',

            'leave_report.view',

            // Performance Period
            'performance_period.view',
            'performance_period.create',
            'performance_period.update',
            'performance_period.delete',

            // Performance Indicator
            'performance_indicator.view',
            'performance_indicator.create',
            'performance_indicator.update',
            'performance_indicator.delete',

            // Performance Review
            'performance_review.view',
            'performance_review.create',
            'performance_review.update',
            'performance_review.delete',
            'performance_review.submit',
            'performance_review.approve',
            'performance_review.reject',

            // Performance Report
            'performance_report.view',

            // Competency
            'competency.view',
            'competency.create',
            'competency.update',
            'competency.delete',

            // Competency Level
            'competency_level.view',
            'competency_level.create',
            'competency_level.update',
            'competency_level.delete',

            // Employee Competency
            'employee_competency.view',
            'employee_competency.create',
            'employee_competency.update',
            'employee_competency.delete',

            // Training
            'training.view',
            'training.create',
            'training.update',
            'training.delete',

            'training.participant.view',
            'training.participant.register',
            'training.participant.update',
            'training.participant.delete',

            'training.status.update',
            'training.history.view',

            'training.evaluation.view',
            'training.evaluation.create',
            'training.evaluation.update',

            // Career Path
            'career_path.view',
            'career_path.create',
            'career_path.update',
            'career_path.delete',

            // Career Path Position
            'career_path_position.view',
            'career_path_position.create',
            'career_path_position.update',
            'career_path_position.delete',

            // Promotion Assessment
            'promotion_assessment.view',
            'promotion_assessment.create',
            'promotion_assessment.update',
            'promotion_assessment.delete',

            // Promotion Assessment Item
            'promotion_assessment_item.view',
            'promotion_assessment_item.create',
            'promotion_assessment_item.update',
            'promotion_assessment_item.delete',

            // Expert System

            // Knowledge Category
            'knowledge_category.view',
            'knowledge_category.create',
            'knowledge_category.update',
            'knowledge_category.delete',

            // Knowledge
            'knowledge.view',
            'knowledge.create',
            'knowledge.update',
            'knowledge.delete',

            // Expert Rule
            'expert_rule.view',
            'expert_rule.create',
            'expert_rule.update',
            'expert_rule.delete',

            // Rule Condition
            'rule_condition.view',
            'rule_condition.create',
            'rule_condition.update',
            'rule_condition.delete',

            // Rule Action
            'rule_action.view',
            'rule_action.create',
            'rule_action.update',
            'rule_action.delete',

            // Recommendation
            'recommendation.view',
            'recommendation.create',
            'recommendation.update',

            // Dashboard
            'dashboard.view',
            'employee_report.view',
            'competency_report.view',
            'expert_system_report.view',
            'recommendation_report.view',

            // System Support
            'activity_log.view',
            'document.view',
            'document.create',
            'document.delete',

            // Notification
            'notification.view',
            'notification.read',
            'notification.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $superAdmin = Role::firstOrCreate([
            'name' => 'super-admin',
            'guard_name' => 'web',
        ]);

        $admin = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        $hrAdmin = Role::firstOrCreate([
            'name' => 'hr-admin',
            'guard_name' => 'web',
        ]);

        $manager = Role::firstOrCreate([
            'name' => 'manager',
            'guard_name' => 'web',
        ]);

        $employee = Role::firstOrCreate([
            'name' => 'employee',
            'guard_name' => 'web',
        ]);

        $superAdmin->syncPermissions($permissions);

        $admin->syncPermissions($permissions);

        $hrAdmin->syncPermissions([
            // User
            'user.view',
            'user.create',
            'user.update',

            // Role
            'role.view',

            // Permission
            'permission.view',

            // Department
            'department.view',
            'department.create',
            'department.update',
            'department.delete',

            // Position
            'position.view',
            'position.create',
            'position.update',
            'position.delete',

            // Position Requirement
            'position_requirement.view',
            'position_requirement.create',
            'position_requirement.update',
            'position_requirement.delete',

            // Position Requirement Competency
            'position_requirement_competency.view',
            'position_requirement_competency.create',
            'position_requirement_competency.update',
            'position_requirement_competency.delete',

            // Employee
            'employee.view',
            'employee.create',
            'employee.update',
            'employee.delete',

            // Attendance
            'attendance.view',
            'attendance.clock_in',
            'attendance.clock_out',
            'attendance.view_all',
            'attendance.report',

            // Leave
            'leave_type.view',
            'leave_type.create',
            'leave_type.update',
            'leave_type.delete',

            'leave_balance.view',
            'leave_balance.view_all',

            'leave_request.view',
            'leave_request.approve',
            'leave_request.reject',
            'leave_request.cancel',

            'leave_report.view',

            // Performance Period
            'performance_period.view',
            'performance_period.create',
            'performance_period.update',
            'performance_period.delete',

            // Performance Indicator
            'performance_indicator.view',
            'performance_indicator.create',
            'performance_indicator.update',
            'performance_indicator.delete',

            // Performance Review
            'performance_review.view',
            'performance_review.create',
            'performance_review.update',
            'performance_review.delete',
            'performance_review.submit',
            'performance_review.approve',
            'performance_review.reject',

            // Performance Report
            'performance_report.view',

            // Competency
            'competency.view',
            'competency.create',
            'competency.update',
            'competency.delete',

            // Competency Level
            'competency_level.view',
            'competency_level.create',
            'competency_level.update',
            'competency_level.delete',

            // Employee Competency
            'employee_competency.view',
            'employee_competency.create',
            'employee_competency.update',
            'employee_competency.delete',
            // Training

            'training.view',
            'training.create',
            'training.update',
            'training.delete',
            // Training Participant
            'training.participant.view',
            'training.participant.register',
            'training.participant.update',
            'training.participant.delete',

            'training.status.update',

            'training.history.view',

            // Training evaluation
            'training.evaluation.view',
            'training.evaluation.create',
            'training.evaluation.update',

            // Career Path
            'career_path.view',
            'career_path.create',
            'career_path.update',
            'career_path.delete',

            // Career Path Position
            'career_path_position.view',
            'career_path_position.create',
            'career_path_position.update',
            'career_path_position.delete',

            // Promotion Assessment
            'promotion_assessment.view',
            'promotion_assessment.create',
            'promotion_assessment.update',
            'promotion_assessment.delete',

            // Promotion Assessment Item
            'promotion_assessment_item.view',
            'promotion_assessment_item.create',
            'promotion_assessment_item.update',
            'promotion_assessment_item.delete',

            // Expert System

            // Knowledge Category
            'knowledge_category.view',
            'knowledge_category.create',
            'knowledge_category.update',
            'knowledge_category.delete',

            // Knowledge
            'knowledge.view',
            'knowledge.create',
            'knowledge.update',
            'knowledge.delete',

            // Expert Rule
            'expert_rule.view',
            'expert_rule.create',
            'expert_rule.update',
            'expert_rule.delete',

            // Rule Condition
            'rule_condition.view',
            'rule_condition.create',
            'rule_condition.update',
            'rule_condition.delete',

            // Rule Action
            'rule_action.view',
            'rule_action.create',
            'rule_action.update',
            'rule_action.delete',

            // Recommendation
            'recommendation.view',
            'recommendation.create',
            'recommendation.update',

            // Dashboard
            'dashboard.view',
            'employee_report.view',
            'competency_report.view',
            'expert_system_report.view',
            'recommendation_report.view',

            // System Support
            'activity_log.view',
            'document.view',
            'document.create',
            'document.delete',

            // Notification
            'notification.view',
            'notification.read',
            'notification.delete',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Manager
        |--------------------------------------------------------------------------
        */

        $manager->syncPermissions([
            // User
            'user.view',

            // Employee
            'employee.view',

            // Performance Period
            'performance_period.view',

            // Performance Indicator
            'performance_indicator.view',

            // Performance Review
            'performance_review.view',
            'performance_review.create',
            'performance_review.update',
            'performance_review.submit',
            'performance_review.approve',
            'performance_review.reject',

            // Performance Report
            'performance_report.view',

            // Training
            'training.view',
            'training.participant.view',
            'training.history.view',
            'training.evaluation.view',
            'training.evaluation.create',
            'training.evaluation.update',

            // Recommendation
            'recommendation.view',
            'recommendation.update',

            // Dashboard
            'dashboard.view',
            'employee_report.view',
            'competency_report.view',
            'expert_system_report.view',
            'recommendation_report.view',

            // Notification
            'notification.view',
            'notification.read',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Employee
        |--------------------------------------------------------------------------
        */

        $employee->syncPermissions([
            // User
            'user.view',

            // Employee
            'employee.view',

            // Attendance
            'attendance.view',
            'attendance.clock_in',
            'attendance.clock_out',

            // Leave
            'leave_balance.view',

            'leave_request.view',
            'leave_request.create',
            'leave_request.cancel',

            // Performance Period
            'performance_period.view',

            // Performance Indicator
            'performance_indicator.view',

            // Performance Review
            'performance_review.view',
            'performance_review.create',
            'performance_review.update',
            'performance_review.submit',

            // Training
            'training.view',
            'training.history.view',

            // Notification
            'notification.view',
            'notification.read',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
