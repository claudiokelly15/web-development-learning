<?php

namespace App\Http\Controllers;

use App\Actions\Payments\FinancialReportExport;
use App\Actions\Payments\MerchantFinancialReports;
use App\Actions\Payments\MerchantNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/** Normal read-only routes; service activation remains explicit and denied by default. */
class P10ReportingController extends Controller
{
    private function merchant(Request $request): string
    {
        Gate::authorize('manageVerification', $request->route('merchant'));

        return $request->route('merchant')->id;
    }

    private function headers(Response $response): Response
    {
        $response->headers->set('Cache-Control','no-store, private');
        $response->headers->set('X-Content-Type-Options','nosniff');
        $response->headers->set('Referrer-Policy','no-referrer');

        return $response;
    }

    public function reports(Request $request): Response
    {
        $merchant = $this->merchant($request);
        abort_unless(app()->bound(MerchantFinancialReports::class),503,'Financial reporting awaits approved financial-store admission.');
        $report = app(MerchantFinancialReports::class)->report($request->user(),$merchant,$request->query(),$this->now());

        return $this->headers(response()->view('merchants.p10',['tab'=>'reports','report'=>$report,'merchantId'=>$merchant]));
    }

    private function now(): int
    {
        return app()->bound('p10.clock') ? (app('p10.clock'))() : (int) floor(microtime(true)*1000);
    }

    public function notifications(Request $request): Response
    {
        $merchant = $this->merchant($request);
        abort_unless($request->query() === [],422);
        abort_unless(app()->bound(MerchantNotificationService::class),503,'Notification history awaits approved outbox provisioning and custody.');

        return $this->headers(response()->view('merchants.p10',['tab'=>'notifications','notifications'=>app(MerchantNotificationService::class)->history($request->user(),$merchant),'merchantId'=>$merchant]));
    }

    public function export(Request $request): Response
    {
        $format = $request->route('format');
        $merchant = $this->merchant($request);
        abort_unless(app()->bound(MerchantFinancialReports::class),503);
        $document = app(MerchantFinancialReports::class)->export($request->user(),$merchant,$request->query(),$this->now(),$format);

        return $this->download($document,$format);
    }

    public function document(Request $request): Response
    {
        $event = $request->route('event');
        $merchant = $this->merchant($request);
        abort_unless($request->query() === [],422);
        abort_unless(app()->bound(MerchantNotificationService::class),503);

        return $this->download(app(MerchantNotificationService::class)->document($request->user(),$merchant,$event),'pdf');
    }

    private function download(array $document, string $format): Response
    {
        return $this->headers(response($document['content'],200,['Content-Type'=>$format === 'pdf' ? 'application/pdf' : 'text/csv; charset=UTF-8','Content-Disposition'=>'attachment; filename="xlipa-'.$document['reference'].'.'.$format.'"','Content-Security-Policy'=>"default-src 'none'; sandbox"]));
    }

    public function chief(Request $request): Response
    {
        abort_unless(array_diff(array_keys($request->query()),['format']) === [] && in_array($request->query('format'),[null,'csv','pdf'],true),422);
        $scope = app(AdminApplicationController::class)->scope($request);
        $counts = (clone $scope)->select('applications.status')->selectRaw('count(*) as count')->groupBy('applications.status')->get();
        $activity = DB::table('audit_logs')->whereIn('event_type',['application.submitted','application.reviewed'])
            ->whereIn('application_id',(clone $scope)->select('applications.id'))->orderByDesc('occurred_at')->orderBy('id')->limit(100)->get(['application_id','event_type','occurred_at']);
        $rows = [['XLipa Chief application reporting'],['Scope','Authorized non-conflicted applications only; latest 100 redacted audit events. No platform revenue or system health assertion.'],['Status','Application count']];
        foreach ($counts as $count) {
            $rows[] = [$count->status,$count->count];
        }
        $rows[] = ['Application','Audit event','Recorded time (source units retained)'];
        foreach ($activity as $event) {
            $rows[] = [$event->application_id,$event->event_type,$event->occurred_at];
        }
        if ($request->query('format') !== null) {
            $format = $request->query('format');

            return $this->download(['reference'=>(string) \Illuminate\Support\Str::uuid(),'content'=>$format === 'csv' ? FinancialReportExport::csv($rows) : FinancialReportExport::pdf('Chief application report',array_map(fn (array $row): string => implode(' | ',$row),$rows))],$format);
        }

        return $this->headers(response()->view('admin.p10',['counts'=>$counts,'activity'=>$activity]));
    }
}
